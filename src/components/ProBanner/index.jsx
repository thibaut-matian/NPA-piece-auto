import "./index.scss";
import useSiteContent from "../../hooks/useSiteContent";
import proIcon from "../../assets/icons/pro.svg";

export default function ProBanner() {
  const { site, home } = useSiteContent();

  return (
    <section className="pro-banner">
      <div className="pro-banner-inner">
        <div className="pro-banner-content">
          <span className="pro-banner-icon">
            <img src={proIcon} alt="" />
          </span>
          <div className="pro-banner-text">
            <div className="pro-banner-heading">
              <h2 className="pro-banner-title">{home.proTitle}</h2>
              <span className="pro-banner-tag">{home.proTag}</span>
            </div>
            <span className="pro-banner-description">
              {home.proDescription}
            </span>
          </div>
        </div>

        <div className="pro-banner-actions">
          <a
            href={site.whatsappHref}
            target="_blank"
            rel="noreferrer"
            className="btn btn-whatsapp"
          >
            WhatsApp Pro : {site.whatsapp}
          </a>
          <a href={site.phoneHref} className="btn btn-light">
            Ligne pro directe
          </a>
        </div>
      </div>
    </section>
  );
}
