from rest_framework.permissions import SAFE_METHODS, BasePermission


class EstAdmin(BasePermission):
    message = "Droits insuffisants : action réservée à l'administrateur."

    def has_permission(self, request, view):
        return bool(request.user and request.user.is_authenticated and request.user.role == "admin")


class LectureOuAdmin(BasePermission):
    """Lecture pour tout le personnel, écriture réservée à l'administrateur."""

    message = EstAdmin.message

    def has_permission(self, request, view):
        if not (request.user and request.user.is_authenticated):
            return False
        return request.method in SAFE_METHODS or request.user.role == "admin"


class SuppressionAdmin(BasePermission):
    """Tout le personnel peut créer et modifier ; seul l'admin supprime (comme en PHP)."""

    message = "Seul l'administrateur peut supprimer cet élément."

    def has_permission(self, request, view):
        if not (request.user and request.user.is_authenticated):
            return False
        return request.method != "DELETE" or request.user.role == "admin"
