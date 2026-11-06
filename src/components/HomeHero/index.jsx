import "./index.scss";
import VehicleSearch from "../VehicleSearch";
import useSiteContent from "../../hooks/useSiteContent";

export default function HomeHero() {
  const { home } = useSiteContent();

  return (
    <section
      className="home-hero"
      style={home.heroImage ? { backgroundImage: `url("${home.heroImage}")` } : undefined}
    >
      <div className="home-hero-inner">
        <div className="home-hero-content">
          <span className="home-hero-badge">
            {home.heroBadge}
          </span>
          <h1 className="home-hero-title">{home.heroTitle}</h1>
          <p className="home-hero-text">{home.heroDescription}</p>
          <ul className="home-hero-features">
            {home.heroFeatures.map((feature) => (
              <li key={feature} className="home-hero-feature">
                {feature}
              </li>
            ))}
          </ul>
        </div>

        <VehicleSearch />
      </div>
    </section>
  );
}
