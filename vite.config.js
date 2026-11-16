import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";
import { validateBuildApi } from "./scripts/validate-build-api.mjs";

export default defineConfig(({ command, mode }) => {
  const prodEnv = loadEnv("production", process.cwd(), "VITE_");
  if (command === "build") {
    const buildEnv = loadEnv(mode, process.cwd(), "VITE_");
    validateBuildApi(buildEnv.VITE_API_URL, process.env.NPA_ALLOW_LOCAL_HTTP_BUILD === "1");
    if (buildEnv.VITE_API_URL?.startsWith("http://")) {
      console.warn("Build HTTP local uniquement : ne pas deployer ce build.");
    }
  }
  return {
    plugins: [react()],
    base: "/",
    server: {
      proxy: {
        "/woo-api": {
          target: prodEnv.VITE_API_URL,
          changeOrigin: true,
          secure: false,
          rewrite: (path) => path.replace(/^\/woo-api/, ""),

          configure: (proxy, _options) => {
            proxy.on("proxyRes", (proxyRes, req, res) => {
              const setCookie = proxyRes.headers["set-cookie"];
              if (setCookie) {
                proxyRes.headers["set-cookie"] = setCookie.map((cookie) =>
                  cookie
                    .replace(/Secure/gi, "")
                    .replace(/SameSite=None/gi, "SameSite=Lax")
                    .replace(/domain=[^;]+/gi, "")
                    .replace(/path=[^;]+/gi, "path=/")
                    .replace(/;\s*;/g, ";"),
                );
              }
            });
          },
        },
      },
    },
  };
});
