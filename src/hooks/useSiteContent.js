import { useSelector } from "react-redux";
import { site as defaultSite } from "../config/site";

const getText = (fields, key, fallback) => {
  const value = fields[key];
  return typeof value === "string" && value.trim() ? value.trim() : fallback;
};

export default function useSiteContent() {
  const fields = useSelector((state) => state.site.contentFields || {});

  const phone = getText(fields, "global_phone", defaultSite.phone);
  const whatsapp = getText(fields, "global_whatsapp", defaultSite.whatsapp);
  const hours = [
    getText(fields, "global_hours_weekdays", defaultSite.hours[0]),
    getText(fields, "global_hours_saturday", defaultSite.hours[1]),
    getText(fields, "global_hours_sunday", defaultSite.hours[2]),
  ];
  const address = {
    street: getText(fields, "global_address_street", defaultSite.address.street),
    zip: getText(fields, "global_address_zip", defaultSite.address.zip),
    city: getText(fields, "global_address_city", defaultSite.address.city),
  };
  const toInternationalNumber = (value) => {
    const digits = value.replace(/\D/g, "");
    return digits.startsWith("0") ? `33${digits.slice(1)}` : digits;
  };

  return {
    site: {
      ...defaultSite,
      name: getText(fields, "global_site_name", defaultSite.name),
      tagline: getText(fields, "global_tagline", defaultSite.tagline),
      description: getText(fields, "global_description", defaultSite.description),
      phone,
      phoneHref: `tel:+${toInternationalNumber(phone)}`,
      whatsapp,
      whatsappHref: `https://wa.me/${toInternationalNumber(whatsapp)}`,
      email: getText(fields, "global_email", defaultSite.email),
      address,
      hours,
      hoursShort: getText(fields, "global_hours_short", defaultSite.hoursShort),
      mapsHref: getText(fields, "global_maps_url", defaultSite.mapsHref),
      accessNote: getText(fields, "global_access_note", defaultSite.accessNote),
      topbar: [1, 2, 3, 4].map((index) =>
        getText(fields, `global_topbar_${index}`, defaultSite.topbar[index - 1]),
      ),
      topbarHighlight: getText(
        fields,
        "global_topbar_highlight",
        defaultSite.topbarHighlight,
      ),
      logoUrl: getText(fields, "global_logo", ""),
      contact: {
        ...defaultSite.contact,
        successTitle: getText(
          fields,
          "contact_success_title",
          defaultSite.contact.successTitle,
        ),
        successText: getText(
          fields,
          "contact_success_text",
          defaultSite.contact.successText,
        ),
      },
      cart: {
        ...defaultSite.cart,
        shippingModes: defaultSite.cart.shippingModes.map((mode) =>
          mode.match === "local_pickup"
            ? {
                ...mode,
                text: `${address.street}, ${address.zip} ${address.city}`,
              }
            : mode,
        ),
      },
    },
    home: {
      heroBadge: getText(fields, "home_hero_badge", "Comptoir stock permanent • 13010 Marseille"),
      heroTitle: getText(fields, "home_hero_title", "Vos pièces auto au meilleur prix immédiatement"),
      heroDescription: getText(
        fields,
        "home_hero_description",
        "Plus de 150 000 références neuves certifiées constructeur en stock ou disponibles sous 2h à notre comptoir de La Capelette. Commandez en ligne, retirez sur place ou faites-vous livrer sur Marseille & alentours.",
      ),
      heroImage: getText(fields, "home_hero_image", ""),
      heroFeatures: getText(
        fields,
        "home_hero_features",
        "Dispo en 2h\n100% neuves OEM\nConseil d'atelier",
      )
        .split(/\r?\n/)
        .map((feature) => feature.trim())
        .filter(Boolean),
      familiesEyebrow: getText(fields, "home_families_eyebrow", "Catalogue express"),
      familiesTitle: getText(fields, "home_families_title", "Les grandes familles en stock"),
      familiesDescription: getText(
        fields,
        "home_families_description",
        "Références de première monte certifiées disponibles immédiatement au comptoir ou en livraison rapide.",
      ),
      familyTitles: Array.from({ length: 6 }, (_, index) =>
        getText(fields, `home_family_${index + 1}_title`, ""),
      ),
      familyDescriptions: Array.from({ length: 6 }, (_, index) =>
        getText(fields, `home_family_${index + 1}_description`, ""),
      ),
      proTitle: getText(fields, "home_pro_title", "Espace pros & garages PACA"),
      proTag: getText(fields, "home_pro_tag", "Tarifs ateliers"),
      proDescription: getText(
        fields,
        "home_pro_description",
        "Navettes quotidiennes express, compte avec facturation mensuelle et remises directes.",
      ),
      reassuranceTitles: Array.from({ length: 4 }, (_, index) =>
        getText(fields, `home_reassurance_${index + 1}_title`, ""),
      ),
      reassuranceDescriptions: Array.from({ length: 4 }, (_, index) =>
        getText(fields, `home_reassurance_${index + 1}_description`, ""),
      ),
    },
  };
}
