from rest_framework import authentication


class SessionAuthentication(authentication.SessionAuthentication):
    """Session Django + vérification CSRF (en-tête X-CSRFToken envoyé par le front)."""

    def authenticate_header(self, request):
        # Renvoie 401 (et non 403) quand la session a expiré : le front sait alors
        # qu'il doit rediriger vers la page de connexion.
        return "Session"


def verifier_csrf(request):
    """Contrôle CSRF explicite pour les formulaires publics (connexion, contact...)."""
    SessionAuthentication().enforce_csrf(request)
