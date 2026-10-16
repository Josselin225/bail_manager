import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import "@fontsource/public-sans/400.css";
import "@fontsource/public-sans/500.css";
import "@fontsource/public-sans/600.css";
import "@fontsource/public-sans/700.css";
import "@fontsource/public-sans/800.css";
import "./styles.css";
import App from "./App";
import { FournisseurUI } from "./components/ui";
import { FournisseurSession, appliquerTheme, themeInitial } from "./lib/session";
import { enregistrerApplication } from "./lib/pwa";

appliquerTheme(themeInitial());

const client = new QueryClient({
  defaultOptions: {
    queries: { retry: (n, e) => n < 1 && (e?.statut ?? 500) >= 500, refetchOnWindowFocus: false, staleTime: 15000 },
  },
});

enregistrerApplication();

ReactDOM.createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <BrowserRouter>
      <QueryClientProvider client={client}>
        <FournisseurUI>
          <FournisseurSession>
            <App />
          </FournisseurSession>
        </FournisseurUI>
      </QueryClientProvider>
    </BrowserRouter>
  </React.StrictMode>,
);
