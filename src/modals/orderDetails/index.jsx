import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { useSelector } from "react-redux";
import SucessMessage from "../../components/SucessMessage";
import { apiFetch } from "../../utils/apiFetch";
import StatusBadge from "../../components/StatusBadge";
import PickupDeadline from "../../components/PickupDeadline";
import "./index.scss";

export default function OrderDetails() {
  const modalProps = useSelector((state) => state.modal.modalProps);
  const orderId = typeof modalProps === "object" ? modalProps?.id : modalProps;
  const token = useSelector((state) => state.user.isAuthenticated);

  const [detail, setDetail] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const productOrigin = (permalink) => {
    if (!permalink) return null;
    const pathSegments = permalink.split("produit/").filter(Boolean);
    return pathSegments.pop() || null;
  };

  useEffect(() => {
    if (!orderId) {
      setDetail(null);
      setLoading(false);
      return;
    }

    // 2. Réinitialisation immédiate : purge l'ancien produit et lance le loader
    setDetail(null);
    setLoading(true);
    setError("");

    let cancelled = false;

    async function fetchOrder() {
      try {
        // Le cookie de connexion identifie le client (requête en lecture : pas de code CSRF)
        const [response, pickupResponse] = await Promise.all([
          apiFetch(`${import.meta.env.VITE_API_URL}/wp-json/wc/store/v1/order/${orderId}`),
          apiFetch(`${import.meta.env.VITE_API_URL}/wp-json/custom/v1/order-confirmation/${orderId}`),
        ]);
        const data = await response.json();
        const pickupData = await pickupResponse.json();
        if (!response.ok) {
          throw new Error(
            data.message || "Impossible de récupérer la commande.",
          );
        }
        if (!pickupResponse.ok) {
          throw new Error(pickupData.message || "Impossible de récupérer le suivi du retrait.");
        }

        if (cancelled) return;
        setDetail({ ...data, pickup: pickupData.pickup, is_paid: pickupData.is_paid });
      } catch (err) {
        if (!cancelled) setError(err.message);
      } finally {
        if (!cancelled) setLoading(false);
      }
    }

    fetchOrder();

    return () => {
      cancelled = true;
    };
  }, [orderId, token]);

  if (loading) return <p>Chargement...</p>;
  if (error) return <p>{error}</p>;
  if (!detail) return null;

  const current = detail;
  const isPaid = current.is_paid;
  const label = current.number ?? current.id;
  const statusText = isPaid
    ? "Paiement confirmé pour la commande n°"
    : "Paiement en attente de confirmation pour la commande n°";

  return (
    <div className="order-details">
      <h5 className={isPaid ? "payment-ok" : "payment-pending"}>
        {statusText}
        {label}
      </h5>
      <StatusBadge status={current.status} />
      <PickupDeadline pickup={current.pickup} />

      {!orderId && <SucessMessage order={current} />}

      <h3>Produits</h3>

      <div className="order-items">
        {detail.items?.map((item) => (
          <Link
            key={item.id}
            to={`/product/${productOrigin(item.permalink)}`}
            className="order-item"
          >
            {item.images?.[0] && (
              <img
                src={item.images[0].thumbnail}
                alt={item.images[0].alt || item.name}
                width={70}
              />
            )}

            <div>
              <strong>{item.name}</strong>
              <p>Quantité : {item.quantity}</p>
              <p>{(Number(item.totals.line_total) / 100).toFixed(2)} €</p>
            </div>
          </Link>
        ))}
      </div>

      {detail.shipping_address && (
        <>
          <h3>Adresse de livraison</h3>

          <p>
            {detail.shipping_address.first_name}{" "}
            {detail.shipping_address.last_name}
          </p>

          <p>{detail.shipping_address.address_1}</p>

          {detail.shipping_address.address_2 && (
            <p>{detail.shipping_address.address_2}</p>
          )}

          <p>
            {detail.shipping_address.postcode} {detail.shipping_address.city}
          </p>

          <p>
            {detail.shipping_address.state} {detail.shipping_address.country}
          </p>

          <p>
            Livraison :{" "}
            {(Number(detail.totals.total_shipping) / 100).toFixed(2)} €
          </p>
          <p>
            Total : {(Number(detail.totals.total_price) / 100).toFixed(2)} €
          </p>
        </>
      )}
    </div>
  );
}
