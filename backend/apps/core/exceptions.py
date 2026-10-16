import logging

from django.core.exceptions import ValidationError as DjangoValidationError
from django.db import IntegrityError
from rest_framework import status
from rest_framework.response import Response
from rest_framework.views import exception_handler

logger = logging.getLogger(__name__)


class RegleMetier(Exception):
    """Violation d'une règle de gestion : le message est affiché tel quel à l'utilisateur."""

    def __init__(self, message, code="regle_metier"):
        super().__init__(message)
        self.message = message
        self.code = code


def gestionnaire_exceptions(exc, context):
    if isinstance(exc, RegleMetier):
        return Response({"detail": exc.message, "code": exc.code}, status=status.HTTP_400_BAD_REQUEST)
    if isinstance(exc, DjangoValidationError):
        return Response({"detail": " ".join(exc.messages)}, status=status.HTTP_400_BAD_REQUEST)
    if isinstance(exc, IntegrityError):
        logger.warning("IntegrityError: %s", exc)
        return Response(
            {"detail": "Opération impossible : cet élément est lié à d'autres données."},
            status=status.HTTP_409_CONFLICT,
        )
    response = exception_handler(exc, context)
    if response is not None and isinstance(response.data, dict) and "detail" not in response.data:
        # Erreurs de validation de champs : on fournit aussi un message lisible global
        premier = next(iter(response.data.values()), None)
        if isinstance(premier, list) and premier:
            response.data["detail"] = str(premier[0])
    return response
