import assert from "node:assert/strict";
import { after, test } from "node:test";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { Provider } from "react-redux";
import { StaticRouter } from "react-router-dom";
import { configureStore } from "@reduxjs/toolkit";
import { createServer } from "vite";

const server = await createServer({ server: { middlewareMode: true, watch: null } });
after(() => server.close());
const { default: VehicleSearch } = await server.ssrLoadModule("/src/components/VehicleSearch/index.jsx");

function render(vehicles, vehicle = null) {
  const state = {
    vehicles: { byParent: {}, loading: {}, error: null, ...vehicles },
    filters: { vehicle },
    site: { contentFields: {} },
  };
  const store = configureStore({ reducer: () => state });
  return renderToStaticMarkup(
    createElement(Provider, { store },
      createElement(StaticRouter, { location: "/" }, createElement(VehicleSearch))),
  );
}

test("explains an empty vehicle list and keeps keyword search available", () => {
  const html = render({ byParent: { 0: [] } });
  assert.match(html, /La recherche par véhicule n&#x27;est pas encore disponible/);
  assert.match(html, /role="status"/);
  const input = html.match(/<input[^>]*id="vehicle-search-reference"[^>]*>/)?.[0];
  assert.ok(input);
  assert.doesNotMatch(input, /disabled/);
  assert.doesNotMatch(html, /Chargement…/);
});

test("distinguishes loading from an empty list", () => {
  const html = render({ loading: { 0: true } });
  assert.match(html, /Chargement des véhicules…/);
  assert.doesNotMatch(html, /pas encore disponible/);
});

test("announces loading errors instead of showing an endless loading placeholder", () => {
  const html = render({ error: "Impossible de charger les véhicules." });
  assert.match(html, /role="alert"/);
  assert.match(html, /Impossible de charger les véhicules/);
  assert.match(html, /référence ou mot-clé ci-dessous/);
  assert.doesNotMatch(html, /Chargement…/);
});

test("explains missing models or engines without requiring all three selections", () => {
  const brand = { id: 1, name: "Renault" };
  const model = { id: 2, name: "Clio" };
  const modelsEmpty = render({ byParent: { 0: [brand], 1: [] } }, { brandId: "1" });
  assert.match(modelsEmpty, /Aucun modèle renseigné/);
  assert.doesNotMatch(modelsEmpty.match(/<button[^>]*type="submit"[^>]*>/)?.[0] || "", /disabled/);
  const enginesEmpty = render(
    { byParent: { 0: [brand], 1: [model], 2: [] } },
    { brandId: "1", modelId: "2" },
  );
  assert.match(enginesEmpty, /Aucune motorisation renseignée/);
});

test("does not display an unavailable message when vehicles are populated", () => {
  const html = render({ byParent: { 0: [{ id: 1, name: "Renault" }] } });
  assert.match(html, /Renault/);
  assert.doesNotMatch(html, /vehicle-search-message/);
});
