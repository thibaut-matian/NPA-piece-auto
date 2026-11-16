import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { Provider } from "react-redux";
import { configureStore } from "@reduxjs/toolkit";
import { MemoryRouter } from "react-router-dom";
import ProductPurchase from "../src/components/ProductPurchase/index.jsx";
import CartSummary from "../src/components/CartSummary/index.jsx";
import CartItem from "../src/components/CartItem/index.jsx";

export function runTaxDisplayTests() {
  let assertions = 0;
  const check = (condition, message) => {
    if (!condition) throw new Error(message);
    assertions += 1;
  };
  const assert = {
    equal: (actual, expected, message) => check(actual === expected, message),
    match: (html, pattern) => check(pattern.test(html), `Missing ${pattern}`),
    doesNotMatch: (html, pattern) => check(!pattern.test(html), `Unexpected ${pattern}`),
  };
  const prices = { price: "990", regular_price: "990", currency_minor_unit: 2 };
  const product = {
    id: 12, name: "Test", prices, npa: { price_excluding_tax: "825" },
    is_in_stock: true, is_purchasable: true, add_to_cart: {}, attributes: [],
  };
  const item = {
    id: 12, key: "test", name: "Test", quantity: 2, prices,
    totals: { line_total: "1500", line_total_tax: "300" },
  };
  const totals = {
    currency_minor_unit: 2, total_items: "1650", total_items_tax: "330",
    total_discount: "150", total_discount_tax: "30", total_price: "1800",
  };
  const render = (Component, props, accountType, isAuthenticated = true) => {
    const state = {
      user: { isAuthenticated, customer: accountType ? { accountType } : null },
      cart: { totals, items: [], shipping_rates: [], needs_shipping: false, loading: false },
      site: { contentFields: {} },
      wishlist: { items: [] },
    };
    const store = configureStore({ reducer: () => state });
    return renderToStaticMarkup(
      React.createElement(Provider, { store },
        React.createElement(MemoryRouter, null, React.createElement(Component, props))),
    );
  };

  for (const [type, authenticated, showHT] of [
    [null, false, false], ["particulier", true, false], ["pro", true, true], ["pro", false, false],
  ]) {
    for (const [Component, props] of [[ProductPurchase, { product }], [CartSummary, {}], [CartItem, { item }]]) {
      const html = render(Component, props, type, authenticated);
      assert.equal(/\bHT\b/.test(html), showHT, `${Component.name}: HT visibility for ${type}/${authenticated}`);
      assert.match(html, /TTC/);
      assert.doesNotMatch(html, /TVA récupérable|TVA 20%/);
    }
  }
  const proProduct = render(ProductPurchase, { product }, "pro");
  assert.match(proProduct, /8,25/);
  const untaxed = { ...product, npa: { price_excluding_tax: "990" } };
  assert.match(render(ProductPurchase, { product: untaxed }, "pro"), /9,90[^<]* HT/);
  const variable = { ...product, has_options: true, prices: { ...prices, price_range: { min_amount: "990" } } };
  assert.match(render(ProductPurchase, { product: variable }, "pro"), /Dès 8,25/);
  assert.match(render(CartSummary, {}, "pro"), /19,80/);
  assert.match(render(CartSummary, {}, "pro"), /16,50/);
  assert.match(render(CartSummary, {}, "pro"), /1,80/);
  assert.match(render(CartSummary, {}, "particulier"), /18,00/);
  assert.match(render(CartItem, { item }, "pro"), /15,00[^<]* HT pour 2/);
  return `PASS: ${assertions} assertions covering guest/private/pro visibility, server HT, variable from-price, TTC and discounted cart totals.`;
}
