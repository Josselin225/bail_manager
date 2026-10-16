import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";

// En développement, le front (5173) relaie l'API et les fichiers vers Django (8000).
// En production, `npm run build` dépose l'interface dans backend/frontend_build,
// servie directement par Django : un seul serveur à lancer.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    host: true,
    proxy: {
      "/api": "http://127.0.0.1:8000",
      "/media": "http://127.0.0.1:8000",
    },
  },
  build: {
    outDir: "../backend/frontend_build",
    emptyOutDir: true,
    chunkSizeWarningLimit: 1200,
  },
});
