from django.contrib.auth.base_user import AbstractBaseUser, BaseUserManager
from django.db import models


class GestionnaireUtilisateurs(BaseUserManager):
    use_in_migrations = True

    def create_user(self, email, password=None, **extra):
        if not email:
            raise ValueError("L'adresse e-mail est obligatoire.")
        user = self.model(email=self.normalize_email(email).lower(), **extra)
        user.set_password(password)
        user.save(using=self._db)
        return user

    def create_superuser(self, email, password=None, **extra):
        extra.setdefault("role", Utilisateur.Role.ADMIN)
        extra.setdefault("nom_complet", "Administrateur")
        return self.create_user(email, password, **extra)


class Utilisateur(AbstractBaseUser):
    class Role(models.TextChoices):
        ADMIN = "admin", "Administrateur"
        AGENT = "agent", "Agent"

    nom_complet = models.CharField(max_length=100)
    email = models.EmailField(max_length=100, unique=True)
    role = models.CharField(max_length=10, choices=Role.choices, default=Role.AGENT)
    session_timeout_minutes = models.PositiveIntegerField(
        "Déconnexion après inactivité (minutes)", default=10
    )
    photo_profil = models.ImageField(upload_to="users/", blank=True, null=True)
    is_active = models.BooleanField(default=True)
    dernier_acces = models.DateTimeField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)

    USERNAME_FIELD = "email"
    EMAIL_FIELD = "email"
    REQUIRED_FIELDS = ["nom_complet"]

    objects = GestionnaireUtilisateurs()

    class Meta:
        db_table = "users"
        ordering = ["nom_complet"]

    def __str__(self):
        return self.nom_complet

    @property
    def est_admin(self):
        return self.role == self.Role.ADMIN
