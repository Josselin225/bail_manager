from django.conf import settings
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.tokens import default_token_generator
from django.core.mail import send_mail
from django.middleware.csrf import get_token
from django.utils import timezone
from django.utils.encoding import force_bytes, force_str
from django.utils.http import urlsafe_base64_decode, urlsafe_base64_encode
from rest_framework import status, viewsets
from rest_framework.decorators import api_view, permission_classes
from rest_framework.exceptions import ValidationError
from rest_framework.permissions import AllowAny, IsAuthenticated
from rest_framework.response import Response

from apps.core.auth import verifier_csrf
from apps.core.models import Parametres
from apps.core.permissions import EstAdmin
from apps.core.utils import enregistrer_echec, ip_client, journaliser, limite_atteinte, reinitialiser_limite

from .models import Utilisateur
from .serializers import ChangementMotDePasseSerializer, ProfilSerializer, UtilisateurSerializer


def _infos_session(request):
    p = Parametres.charger()
    u = request.user
    return {
        "utilisateur": ProfilSerializer(u, context={"request": request}).data if u.is_authenticated else None,
        "entreprise": {
            "nom": p.nom_entreprise,
            "logo": request.build_absolute_uri(p.logo.url) if p.logo else None,
            "couleur": p.couleur_principale,
            "telephone": p.contact_telephone,
            "email": p.contact_email,
        },
        "version": settings.APP_VERSION,
    }


@api_view(["GET"])
@permission_classes([AllowAny])
def session(request):
    """Point d'entrée du front : renvoie l'utilisateur connecté et pose le cookie CSRF."""
    get_token(request)
    return Response(_infos_session(request))


@api_view(["POST"])
@permission_classes([AllowAny])
def connexion(request):
    verifier_csrf(request)
    ip = ip_client(request)
    attente = limite_atteinte("login", ip)
    if attente:
        minutes = max(1, attente // 60)
        return Response(
            {"detail": f"Trop de tentatives. Réessayez dans {minutes} minute(s).", "attente": attente},
            status=status.HTTP_429_TOO_MANY_REQUESTS,
        )
    email = (request.data.get("email") or "").strip().lower()
    user = authenticate(request, email=email, password=request.data.get("password") or "")
    if user is None or not user.is_active:
        enregistrer_echec("login", ip)
        return Response({"detail": "Identifiants incorrects."}, status=status.HTTP_400_BAD_REQUEST)
    reinitialiser_limite("login", ip)
    login(request, user)
    request.session.set_expiry(user.session_timeout_minutes * 60)
    user.dernier_acces = timezone.now()
    user.save(update_fields=["dernier_acces"])
    journaliser(request, "Connexion", f"Connexion réussie depuis {ip}")
    return Response(_infos_session(request))


@api_view(["POST"])
@permission_classes([AllowAny])
def deconnexion(request):
    if request.user.is_authenticated:
        journaliser(request, "Déconnexion", "")
    logout(request)
    return Response({"ok": True})


@api_view(["GET", "PATCH"])
@permission_classes([IsAuthenticated])
def profil(request):
    if request.method == "GET":
        return Response(ProfilSerializer(request.user, context={"request": request}).data)
    ser = ProfilSerializer(request.user, data=request.data, partial=True, context={"request": request})
    ser.is_valid(raise_exception=True)
    ser.save()
    request.session.set_expiry(request.user.session_timeout_minutes * 60)
    journaliser(request, "Profil", "Mise à jour du profil")
    return Response(ser.data)


@api_view(["POST"])
@permission_classes([IsAuthenticated])
def changer_mot_de_passe(request):
    ser = ChangementMotDePasseSerializer(data=request.data)
    ser.is_valid(raise_exception=True)
    if not request.user.check_password(ser.validated_data["ancien"]):
        raise ValidationError({"ancien": "Mot de passe actuel incorrect."})
    request.user.set_password(ser.validated_data["nouveau"])
    request.user.save()
    login(request, request.user)  # garde la session ouverte après changement
    request.session.set_expiry(request.user.session_timeout_minutes * 60)
    journaliser(request, "Mot de passe", "Mot de passe modifié")
    return Response({"detail": "Mot de passe modifié."})


@api_view(["POST"])
@permission_classes([AllowAny])
def mot_de_passe_oublie(request):
    verifier_csrf(request)
    ip = ip_client(request)
    if limite_atteinte("forgot", ip, maximum=3, fenetre=900):
        return Response({"detail": "Trop de demandes. Réessayez plus tard."}, status=429)
    enregistrer_echec("forgot", ip, fenetre=900)
    email = (request.data.get("email") or "").strip().lower()
    user = Utilisateur.objects.filter(email=email, is_active=True).first()
    if user:
        uid = urlsafe_base64_encode(force_bytes(user.pk))
        jeton = default_token_generator.make_token(user)
        lien = f"{settings.URL_APPLICATION}/reinitialiser/{uid}/{jeton}"
        nom = Parametres.charger().nom_entreprise
        send_mail(
            f"{nom} — Réinitialisation du mot de passe",
            f"Bonjour {user.nom_complet},\n\nPour choisir un nouveau mot de passe, ouvrez ce lien "
            f"(valable 3 jours) :\n{lien}\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            None,
            [user.email],
            fail_silently=True,
        )
        journaliser(request, "Mot de passe oublié", f"Lien envoyé à {email}")
    # Réponse identique que l'adresse existe ou non (pas de fuite d'information)
    return Response({"detail": "Si cette adresse existe, un lien de réinitialisation vient d'être envoyé."})


@api_view(["POST"])
@permission_classes([AllowAny])
def reinitialiser_mot_de_passe(request):
    verifier_csrf(request)
    from django.contrib.auth.password_validation import validate_password

    try:
        uid = force_str(urlsafe_base64_decode(request.data.get("uid", "")))
        user = Utilisateur.objects.get(pk=uid)
    except Exception:
        user = None
    if user is None or not default_token_generator.check_token(user, request.data.get("jeton", "")):
        return Response({"detail": "Lien invalide ou expiré."}, status=400)
    pwd = request.data.get("password") or ""
    validate_password(pwd, user)
    user.set_password(pwd)
    user.save()
    journaliser(request, "Mot de passe", f"Réinitialisation pour {user.email}")
    return Response({"detail": "Mot de passe réinitialisé. Vous pouvez vous connecter."})


class UtilisateurViewSet(viewsets.ModelViewSet):
    queryset = Utilisateur.objects.all()
    serializer_class = UtilisateurSerializer
    permission_classes = [EstAdmin]
    search_fields = ["nom_complet", "email"]
    pagination_class = None

    def perform_create(self, ser):
        u = ser.save()
        journaliser(self.request, "Utilisateur", f"Création de {u.nom_complet} ({u.role})")

    def perform_update(self, ser):
        u = ser.save()
        journaliser(self.request, "Utilisateur", f"Modification de {u.nom_complet}")

    def perform_destroy(self, instance):
        if instance.pk == self.request.user.pk:
            raise ValidationError({"detail": "Vous ne pouvez pas supprimer votre propre compte."})
        if instance.est_admin and Utilisateur.objects.filter(role="admin").count() <= 1:
            raise ValidationError({"detail": "Impossible de supprimer le dernier administrateur."})
        journaliser(self.request, "Utilisateur", f"Suppression de {instance.nom_complet}")
        instance.delete()
