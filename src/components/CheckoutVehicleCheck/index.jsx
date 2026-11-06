import "./index.scss";

export default function CheckoutVehicleCheck({ value, onChange, error }) {
  return (
    <section className="checkout-vehicle-check">
      <h3>Vérification de compatibilité (facultatif)</h3>
      <p className="checkout-vehicle-check__description">
        Indiquez l'immatriculation ou le VIN si vous souhaitez que l'équipe
        vérifie manuellement la compatibilité des pièces avant leur préparation.
        Cette information ne lance pas une recherche automatique.
      </p>
      <label className="checkout-vehicle-check__label" htmlFor="checkout-vehicle-check">
        Immatriculation ou numéro VIN
      </label>
      <input
        id="checkout-vehicle-check"
        className="checkout-vehicle-check__input"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder="Ex. AB-123-CD ou numéro VIN"
        aria-invalid={Boolean(error)}
        aria-describedby={error ? "checkout-vehicle-check-error" : undefined}
        maxLength={20}
      />
      {error && (
        <p
          id="checkout-vehicle-check-error"
          className="checkout-vehicle-check__error"
          role="alert"
        >
          {error}
        </p>
      )}
    </section>
  );
}
