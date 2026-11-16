import { test } from "node:test";
import assert from "node:assert/strict";
import { validateBuildApi } from "./validate-build-api.mjs";

test("accepts HTTPS APIs and the explicitly configured same-origin proxy", () => {
  for (const url of ["https://api.example.invalid", "https://example.invalid/wp", "/woo-api"]) {
    assert.doesNotThrow(() => validateBuildApi(url));
  }
});

test("rejects insecure, missing, malformed and ambiguous API URLs", () => {
  for (const url of [
    undefined, "", "http://example.invalid", "http://localhost/wordpress",
    "//example.invalid", "/other", " https://example.invalid", "https://example.invalid?x=1",
    "https://user:password@example.invalid", "https://example.invalid/#fragment",
  ]) {
    assert.throws(() => validateBuildApi(url), undefined, String(url));
  }
});

test("local HTTP exception is explicit and restricted to loopback", () => {
  for (const url of ["http://localhost/wordpress", "http://127.0.0.1:8080", "http://[::1]:8080"]) {
    assert.doesNotThrow(() => validateBuildApi(url, true));
  }
  for (const url of ["http://example.invalid", "http://localhost.example.invalid", "http://192.168.1.1"]) {
    assert.throws(() => validateBuildApi(url, true));
  }
});
