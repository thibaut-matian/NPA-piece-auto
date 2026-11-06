import assert from "node:assert/strict";
import { after, test } from "node:test";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { createServer } from "vite";

const server = await createServer({ server: { middlewareMode: true, watch: null } });
after(() => server.close());
const { default: CheckoutVehicleCheck } = await server.ssrLoadModule(
  "/src/components/CheckoutVehicleCheck/index.jsx",
);

function render(props = {}) {
  return renderToStaticMarkup(
    createElement(CheckoutVehicleCheck, {
      value: "",
      onChange: () => {},
      error: "",
      ...props,
    }),
  );
}

test("explains that the checkout vehicle check is optional and manual", () => {
  const html = render();
  assert.match(html, /Vérification de compatibilité \(facultatif\)/);
  assert.match(html, /vérifie manuellement la compatibilité/);
  assert.match(html, /ne lance pas une recherche automatique/);
  assert.match(html, /Immatriculation ou numéro VIN/);
  assert.doesNotMatch(html, /required/);
});

test("keeps a previously provided vehicle value visible", () => {
  const html = render({ value: "AB-123-CD" });
  assert.match(html, /value="AB-123-CD"/);
});

test("announces invalid vehicle input accessibly", () => {
  const html = render({ value: "invalid", error: "Format invalide." });
  assert.match(html, /aria-invalid="true"/);
  assert.match(html, /aria-describedby="checkout-vehicle-check-error"/);
  assert.match(html, /role="alert"/);
  assert.match(html, /Format invalide\./);
});
