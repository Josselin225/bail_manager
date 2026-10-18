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
    // changeOrigin: false — Django voit l'adresse réellement ouverte (localhost:5173, IP du poste…) : les liens
    // vers les photos qu'il construit restent sur la même origine, donc la session de l'utilisateur les accompagne.
    proxy: {
      "/api": { target: "http://127.0.0.1:8000", changeOrigin: false },
      "/media": { target: "http://127.0.0.1:8000", changeOrigin: false },
    },
  },
  build: {
    outDir: "../backend/frontend_build",
    emptyOutDir: true,
    chunkSizeWarningLimit: 1200,
  },
});
