export default function PickupDeadline({ pickup }) {
  if (!pickup?.deadline) return null;

  return (
    <p>
      Retrait avant le{" "}
      <time dateTime={pickup.deadline}>
        {pickup.deadline_label}
      </time>{" "}
      (heure du magasin). Passé ce délai, contactez le magasin.
    </p>
  );
}
