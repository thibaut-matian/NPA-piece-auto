import "./index.scss";
import useSiteContent from "../../hooks/useSiteContent";
import storeIcon from "../../assets/icons/store.svg";
import truckIcon from "../../assets/icons/truck.svg";
import shieldIcon from "../../assets/icons/shield.svg";
import phoneIcon from "../../assets/icons/phone.svg";

export default function Reassurance() {
  const { site, home } = useSiteContent();
  const items = [
    {
      title: home.reassuranceTitles[0] || "Retrait 2h Capelette",
      text: home.reassuranceDescriptions[0] || "Comptoir 158 Av. Capelette, gratuit",
      icon: storeIcon,
      highlight: true,
    },
    {
      title: home.reassuranceTitles[1] || "Livraison 24/48h",
      text: home.reassuranceDescriptions[1] || "Garages & particuliers sur toute la région",
      icon: truckIcon,
    },
    {
      title: home.reassuranceTitles[2] || "100% neuves OEM",
      text: home.reassuranceDescriptions[2] || "Qualité première monte certifiée",
      icon: shieldIcon,
    },
    {
      title: home.reassuranceTitles[3] || "Conseils experts",
      text: home.reassuranceDescriptions[3] || `Support marseillais au ${site.phone}`,
      icon: phoneIcon,
    },
  ];

  return (
    <section className="reassurance" aria-label="Nos engagements">
      <div className="reassurance-grid">
        {items.map((item) => (
          <div key={item.title} className="reassurance-card">
            <span
              className={`reassurance-icon ${
                item.highlight ? "reassurance-icon-highlight" : ""
              }`}
            >
              <img src={item.icon} alt="" />
            </span>
            <div className="reassurance-content">
              <span className="reassurance-title">{item.title}</span>
              <span className="reassurance-text">{item.text}</span>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
