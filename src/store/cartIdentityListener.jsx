import { createListenerMiddleware } from "@reduxjs/toolkit";
import {
  loginThunk,
  registerThunk,
  logoutThunk,
  fetchCurrentCustomerThunk,
  fetchCurrentUserOrdersThunk,
} from "../thunkActionsCreator/userThunks";
import {
  initializeCartThunk,
  addProductToCart,
  applyCouponThunk,
  selectShippingRateThunk,
} from "../thunkActionsCreator/cartThunks";
import { resetCartToken, setRestoringCart } from "../slices/cartSlice";
import { mergeGuestWishlistThunk } from "../thunkActionsCreator/wishlistThunks";
import { logout } from "../slices/userSlice";
import { resetToGuestWishlist } from "../slices/wishlistSlice";
import { showToast } from "../slices/toastSlice";

// Le nonce Store API est lie a l'identite (invite vs client connecte via le
// cookie de connexion). Quand cette identite change, on redemande un panier/nonce frais
// plutot que de garder celui de l'ancienne identite en memoire/localStorage.
// La wishlist invite vit en localStorage (pas de session cote WooCommerce
// comme pour le panier) : a la connexion on la fusionne dans le compte, a la
// deconnexion on retombe sur la wishlist locale de l'invite.
export const cartIdentityListener = createListenerMiddleware();

cartIdentityListener.startListening({
  matcher: (action) =>
    loginThunk.fulfilled.match(action) ||
    registerThunk.fulfilled.match(action) ||
    logout.match(action),
  effect: async (action, listenerApi) => {
    const previousState = listenerApi.getOriginalState();
    const guestCart = !previousState.user.isAuthenticated && !logout.match(action)
      ? previousState.cart
      : null;
    listenerApi.dispatch(setRestoringCart(true));
    listenerApi.dispatch(resetCartToken());

    if (logout.match(action)) {
      listenerApi.dispatch(resetToGuestWishlist());
    } else {
      listenerApi.dispatch(fetchCurrentCustomerThunk());
      listenerApi.dispatch(fetchCurrentUserOrdersThunk());
      listenerApi.dispatch(mergeGuestWishlistThunk());
    }

    try {
      await listenerApi.dispatch(initializeCartThunk()).unwrap();

      for (const item of guestCart?.items || []) {
        const current = listenerApi.getState().cart.items.find((entry) => entry.key === item.key);
        // WooCommerce peut avoir repris le panier via son cookie : ne pas le doubler.
        const quantity = item.quantity - (current?.quantity || 0);
        if (quantity > 0) {
          await listenerApi.dispatch(addProductToCart({
            productId: item.id,
            quantity,
            variation: Object.fromEntries(
              (item.variation || []).map(({ attribute, value }) => [attribute, value]),
            ),
          })).unwrap();
        }
      }

      for (const coupon of guestCart?.coupons || []) {
        if (!listenerApi.getState().cart.coupons.some((entry) => entry.code === coupon.code)) {
          await listenerApi.dispatch(applyCouponThunk({ code: coupon.code })).unwrap();
        }
      }

      for (const pack of guestCart?.shipping_rates || []) {
        const selected = pack.shipping_rates.find((rate) => rate.selected);
        const current = listenerApi.getState().cart.shipping_rates
          .find((entry) => entry.package_id === pack.package_id)
          ?.shipping_rates.find((rate) => rate.rate_id === selected?.rate_id);
        if (current && !current.selected) {
          await listenerApi.dispatch(selectShippingRateThunk({
            packageId: pack.package_id,
            rateId: current.rate_id,
          })).unwrap();
        }
      }
    } catch (error) {
      listenerApi.dispatch(showToast(
        `Impossible de reprendre le panier : ${error instanceof Error ? error.message : error}`,
      ));
    } finally {
      listenerApi.dispatch(setRestoringCart(false));
    }
  },
});

// Une fois le cookie effacé par WordPress, on déconnecte le client dans le site
cartIdentityListener.startListening({
  actionCreator: logoutThunk.fulfilled,
  effect: (_, listenerApi) => {
    listenerApi.dispatch(logout());
  },
});
