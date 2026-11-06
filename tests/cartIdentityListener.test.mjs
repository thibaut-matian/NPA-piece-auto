import assert from "node:assert/strict";
import { after, test } from "node:test";
import { configureStore } from "@reduxjs/toolkit";
import { createServer } from "vite";

const server = await createServer({
  server: { middlewareMode: true, watch: null },
});
after(() => server.close());

const { cartIdentityListener } = await server.ssrLoadModule("/src/store/cartIdentityListener.jsx");
const { cartSlice } = await server.ssrLoadModule("/src/slices/cartSlice.jsx");
const { userSlice, logout } = await server.ssrLoadModule("/src/slices/userSlice.jsx");
const { toastSlice } = await server.ssrLoadModule("/src/slices/toastSlice.jsx");
const { loginThunk, registerThunk } = await server.ssrLoadModule("/src/thunkActionsCreator/userThunks.jsx");

const originalFetch = globalThis.fetch;
const originalStorage = Object.getOwnPropertyDescriptor(globalThis, "localStorage");
Object.defineProperty(globalThis, "localStorage", {
  configurable: true,
  value: { getItem: () => null, setItem() {}, removeItem() {} },
});
after(() => {
  globalThis.fetch = originalFetch;
  if (originalStorage) Object.defineProperty(globalThis, "localStorage", originalStorage);
  else delete globalThis.localStorage;
});

const item = (id, quantity, variation = []) => ({ id, key: `key-${id}`, quantity, variation });
const cart = (items = [], extra = {}) => ({
  items, coupons: [], shipping_rates: [], needs_shipping: false,
  totals: { total_price: "990", currency_minor_unit: 2 }, ...extra,
});

async function transfer({ guest, account, action = registerThunk, authenticated = false, fail = false }) {
  let saved = structuredClone(account);
  const calls = [];
  globalThis.fetch = async (url, options = {}) => {
    const path = new URL(url, "http://localhost").pathname;
    const body = options.body ? JSON.parse(options.body) : null;
    calls.push({ path, body, headers: options.headers });
    const response = (data, status = 200) => new Response(JSON.stringify(data), {
      status, headers: { Nonce: "account-nonce", "Cart-Token": "account-token" },
    });
    if (path.endsWith("/cart")) return response(saved);
    if (path.endsWith("/cart/add-item")) {
      if (fail) return response({ message: "Stock insuffisant." }, 400);
      const current = saved.items.find((entry) => entry.id === body.id);
      if (current) current.quantity += body.quantity;
      else saved.items.push(item(body.id, body.quantity, body.variation));
      return response(saved, 201);
    }
    if (path.endsWith("/cart/apply-coupon")) {
      saved.coupons.push({ code: body.code });
      return response(saved);
    }
    if (path.endsWith("/cart/select-shipping-rate")) {
      saved.shipping_rates[0].shipping_rates.forEach((rate) => {
        rate.selected = rate.rate_id === body.rate_id;
      });
      return response(saved);
    }
    if (path.endsWith("/wishlist") || path.endsWith("/orders")) return response([]);
    if (path.endsWith("/customer")) return response({});
    throw new Error(`Unexpected request: ${path}`);
  };
  const store = configureStore({
    reducer: { cart: cartSlice.reducer, user: userSlice.reducer, toast: toastSlice.reducer },
    preloadedState: {
      cart: { ...cartSlice.getInitialState(), ...guest, cartToken: "guest-token" },
      user: { ...userSlice.getInitialState(), isAuthenticated: authenticated },
    },
    middleware: (getDefaultMiddleware) => getDefaultMiddleware().prepend(cartIdentityListener.middleware),
  });
  let unsubscribe;
  const finished = new Promise((resolve) => {
    let started = false;
    unsubscribe = store.subscribe(() => {
      if (store.getState().cart.restoring) started = true;
      if (started && !store.getState().cart.restoring) resolve();
    });
  });
  const timeout = setTimeout(() => assert.fail("Cart transfer did not finish"), 5000);
  try {
    store.dispatch(action === logout ? logout() : action.fulfilled({
      profile: { email: "test@example.invalid" }, csrf: "account-csrf",
    }, "test-request"));
    await finished;
    return { state: store.getState(), calls };
  } finally {
    clearTimeout(timeout);
    unsubscribe();
  }
}

for (const action of [registerThunk, loginThunk]) {
  test(`${action.typePrefix}: preserves guest quantities and account-only items`, async () => {
    const { state, calls } = await transfer({
      action, guest: cart([item(12, 3)]), account: cart([item(13, 1)]),
    });
    assert.deepEqual(state.cart.items.map(({ id, quantity }) => ({ id, quantity })), [
      { id: 13, quantity: 1 }, { id: 12, quantity: 3 },
    ]);
    assert.equal(state.cart.cartToken, "account-token");
    const initialRequest = calls.find(({ path }) => path.endsWith("/cart"));
    assert.equal(initialRequest.headers["Cart-Token"], undefined);
    const add = calls.find(({ path }) => path.endsWith("/cart/add-item"));
    assert.equal(add.headers["Cart-Token"], "account-token");
    assert.equal(add.headers["X-NPA-CSRF"], "account-csrf");
  });
}

test("keeps the largest quantity without duplicating an already migrated cart", async () => {
  const { state, calls } = await transfer({
    guest: cart([item(12, 2), item(13, 4)]), account: cart([item(12, 3), item(13, 1)]),
  });
  assert.deepEqual(state.cart.items.map(({ quantity }) => quantity), [3, 4]);
  assert.deepEqual(calls.filter(({ path }) => path.endsWith("/cart/add-item")).map(({ body }) => body),
    [{ id: 13, quantity: 3, variation: [] }]);
});

test("preserves variations, coupons and an available shipping choice", async () => {
  const shipping = (selected) => [{
    package_id: 0, shipping_rates: [
      { rate_id: "local_pickup:1", selected },
      { rate_id: "flat_rate:2", selected: !selected },
    ],
  }];
  const { state, calls } = await transfer({
    guest: cart([item(21, 2, [{ attribute: "pa_size", value: "large" }])], {
      coupons: [{ code: "test10" }], shipping_rates: shipping(true),
    }),
    account: cart([], { shipping_rates: shipping(false) }),
  });
  assert.deepEqual(calls.find(({ path }) => path.endsWith("/cart/add-item")).body.variation,
    [{ attribute: "pa_size", value: "large" }]);
  assert.deepEqual(state.cart.coupons, [{ code: "test10" }]);
  assert.equal(state.cart.shipping_rates[0].shipping_rates[0].selected, true);
});

test("does not reapply coupons or shipping already restored by WooCommerce", async () => {
  const same = cart([item(12, 2)], {
    coupons: [{ code: "test10" }],
    shipping_rates: [{ package_id: 0, shipping_rates: [{ rate_id: "local_pickup:1", selected: true }] }],
  });
  const { calls } = await transfer({ guest: same, account: same });
  assert.equal(calls.filter(({ body }) => body !== null).length, 0);
});

test("reports a rejected transfer and always releases the loading state", async () => {
  const { state } = await transfer({
    guest: cart([item(12, 1)]), account: cart(), fail: true,
  });
  assert.equal(state.cart.restoring, false);
  assert.equal(state.cart.loading, false);
  assert.deepEqual(state.cart.items, []);
  assert.match(state.toast.message, /Impossible de reprendre le panier.*Stock insuffisant/);
});

test("logout does not copy account items into the guest cart", async () => {
  const { state, calls } = await transfer({
    guest: cart([item(12, 2)]), account: cart(), action: logout, authenticated: true,
  });
  assert.deepEqual(state.cart.items, []);
  assert.equal(calls.some(({ path }) => path.endsWith("/cart/add-item")), false);
});
