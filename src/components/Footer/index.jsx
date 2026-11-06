import "./index.scss";

import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import useSiteContent from "../../hooks/useSiteContent";
import logo from "../../assets/images/logo.png";

const COOKIE_NOTICE_KEY = "npa_cookie_notice_dismissed";

export default function Footer() {
  const { site } = useSiteContent();
  const [cookieNoticeOpen, setCookieNoticeOpen] = useState(false);

  useEffect(() => {
    try {
      setCookieNoticeOpen(localStorage.getItem(COOKIE_NOTICE_KEY) !== "true");
    } catch {
      setCookieNoticeOpen(true);
    }
  }, []);

  const dismissCookieNotice = () => {
    setCookieNoticeOpen(false);
    try {
      localStorage.setItem(COOKIE_NOTICE_KEY, "true");
    } catch {
      // L'information restera accessible depuis le pied de page.
    }
  };

  return (
    <>
      <footer className="footer">
        <div className="footer-grid">
          <div className="footer-col">
            <Link to="/" className="footer-brand">
              <img src={site.logoUrl || logo} alt={site.name} className="footer-logo" />
              <span className="footer-brand-name">{site.name}</span>
            </Link>
            <span className="footer-text">{site.description}</span>
            <div className="footer-contact">
              <span>
                {site.address.street}, {site.address.zip} {site.address.city}
              </span>
              <span>
                <a href={site.phoneHref}>{site.phone}</a> / WhatsApp :{" "}
                <a href={site.whatsappHref} target="_blank" rel="noreferrer">
                  {site.whatsapp}
                </a>
              </span>
              <a href={`mailto:${site.email}`}>{site.email}</a>
            </div>
          </div>

          <div className="footer-col">
            <h2 className="footer-title">Accès &amp; horaires comptoir</h2>
            {site.hours.map((line) => (
              <span key={line} className="footer-hours">
                {line}
              </span>
            ))}
            <div className="footer-access">
              <span className="footer-access-title">Repère accès facile</span>
              <span className="footer-text">{site.accessNote}</span>
            </div>
          </div>

          <div className="footer-col">
            <h2 className="footer-title">Une question ?</h2>
            <Link to="/a-propos" className="footer-cta">
              À propos de NPA
            </Link>
            <Link to="/contact" className="footer-cta">
              Contactez-nous
            </Link>
          </div>
        </div>
      </footer>

      <div className="footer-bottom">
        <span className="footer-copyright">
          © {new Date().getFullYear()} {site.name}. Tous droits réservés.
        </span>
        <nav className="footer-legal" aria-label="Informations légales">
          <Link to="/mentions-legales">Mentions légales</Link>
          <Link to="/cgv">Conditions générales de vente</Link>
          <Link to="/cgu">Conditions générales d'utilisation</Link>
          <Link to="/faq">FAQ</Link>
          <Link to="/politique-de-confidentialite">Politique de confidentialité</Link>
          <button
            type="button"
            className="footer-cookie-settings"
            onClick={() => setCookieNoticeOpen(true)}
          >
            Informations cookies
          </button>
        </nav>
      </div>
      {cookieNoticeOpen && (
        <aside
          className="cookie-notice"
          aria-label="Informations sur les cookies"
        >
          <div className="cookie-notice-content">
            <h2 className="cookie-notice-title">Cookies et stockage technique</h2>
            <p className="cookie-notice-text">
              Le site utilise des cookies et stockages techniques nécessaires à la
              connexion, au panier et au paiement. Aucun outil de mesure d'audience
              ou de publicité n'est actuellement intégré.
            </p>
            <p className="cookie-notice-text">
              Consultez notre{" "}
              <Link to="/politique-de-confidentialite">
                politique de confidentialité
              </Link>
              .
            </p>
          </div>
          <button
            type="button"
            className="cookie-notice-dismiss"
            onClick={dismissCookieNotice}
          >
            Fermer
          </button>
        </aside>
      )}
    </>
  );
}
