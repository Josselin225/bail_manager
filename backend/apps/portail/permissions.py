from rest_framework.permissions import BasePermission


class EstLocataire(BasePermission):
    message = "Session expirée. Reconnectez-vous à votre espace locataire."

    def has_permission(self, request, view):
        return bool(request.session.get("locataire_id"))
