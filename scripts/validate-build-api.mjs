export function validateBuildApi(value, allowLocalHttp = false) {
  if (value === "/woo-api") return;
  if (typeof value !== "string" || value !== value.trim() || !/^https?:\/\//i.test(value)) {
    throw new Error("VITE_API_URL doit etre une URL HTTPS absolue ou /woo-api.");
  }

  let url;
  try {
    url = new URL(value);
  } catch {
    throw new Error("VITE_API_URL n'est pas une URL valide.");
  }

  if (url.username || url.password || url.search || url.hash) {
    throw new Error("VITE_API_URL ne doit pas contenir d'identifiants, de requete ou de fragment.");
  }
  if (url.protocol === "https:") return;
  if (allowLocalHttp && ["localhost", "127.0.0.1", "[::1]"].includes(url.hostname)) return;

  throw new Error(
    "Build bloque : VITE_API_URL doit utiliser HTTPS. Pour un test local uniquement, " +
    "NPA_ALLOW_LOCAL_HTTP_BUILD=1 autorise une API HTTP sur localhost ou loopback.",
  );
}
