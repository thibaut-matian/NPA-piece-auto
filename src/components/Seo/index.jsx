import { Helmet } from "react-helmet-async";
import { useSelector } from "react-redux";
import { site } from "../../config/site";

const stripHtml = (value) =>
  String(value || "")
    .replace(/<[^>]*>/g, " ")
    .replace(/\s+/g, " ")
    .trim();

function Seo({
  title,
  description,
  image,
  url,
  type = "website",
  jsonLd,
  noIndex = false,
  lang = "fr",
}) {
  /* On charge dynamiquement le titre et description par defaut du site depuis le slice site*/
  const {
    name,
    description: siteDescription,
    faviconUrl,
    contentFields,
  } = useSelector((state) => state.site);

  const cleanTitle = stripHtml(title);
  const cleanDescription = stripHtml(description);
  const finalTitle =
    cleanTitle ||
    stripHtml(contentFields?.global_site_name) ||
    stripHtml(name) ||
    site.name;
  const finalDescription =
    cleanDescription ||
    stripHtml(contentFields?.global_description) ||
    stripHtml(siteDescription) ||
    site.description;
  const cardType = image ? "summary_large_image" : "summary";

  return (
    <Helmet htmlAttributes={{ lang }}>
      <title>{finalTitle}</title>
      <meta name="description" content={finalDescription} />
      {faviconUrl && <link rel="icon" href={faviconUrl} />}

      <meta property="og:title" content={finalTitle} />
      <meta property="og:description" content={finalDescription} />
      <meta property="og:type" content={type} />
      <meta name="twitter:card" content={cardType} />

      {url && <meta property="og:url" content={url} />}
      {url && <link rel="canonical" href={url} />}
      {image && <meta property="og:image" content={image} />}

      {noIndex && <meta name="robots" content="noindex, nofollow" />}

      {jsonLd && (
        <script type="application/ld+json">{JSON.stringify(jsonLd)}</script>
      )}
    </Helmet>
  );
}

export default Seo;
