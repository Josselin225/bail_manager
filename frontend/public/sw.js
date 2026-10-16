/*
 * Service worker de BailManager (application installable).
 * - Fichiers de l'application (/assets/, nommés par empreinte) : cache d'abord, ils ne changent jamais.
 * - Pages : réseau d'abord ; hors connexion, la dernière version de l'application est servie.
 * - API et fichiers privés (/api/, /media/) : jamais mis en cache (données personnelles, toujours à jour).
 */
const VERSION = "bm-v1";
const COQUILLE = "/index.html";

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll([COQUILLE, "/favicon.svg", "/icone-192.png"])).then(() => self.skipWaiting()));
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys().then((cles) => Promise.all(cles.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== "GET" || url.origin !== self.location.origin) return;
  if (url.pathname.startsWith("/api/") || url.pathname.startsWith("/media/")) return;

  if (url.pathname.startsWith("/assets/") || url.pathname.startsWith("/vitrine/")) {
    e.respondWith(
      caches.match(req).then((trouve) => trouve || fetch(req).then((rep) => {
        if (rep.ok) caches.open(VERSION).then((c) => c.put(req, rep.clone()));
        return rep;
      })),
    );
    return;
  }

  if (req.mode === "navigate") {
    e.respondWith(
      fetch(req)
        .then((rep) => {
          // Les annonces portent des balises d'aperçu propres à chaque bien : on garde la page générique.
          if (rep.ok && !url.pathname.startsWith("/nos-maisons/")) caches.open(VERSION).then((c) => c.put(COQUILLE, rep.clone()));
          return rep;
        })
        .catch(() => caches.match(COQUILLE)),
    );
  }
});
