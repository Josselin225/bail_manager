from django.apps import AppConfig


class ConnecteursConfig(AppConfig):
    name = "apps.connecteurs"
    verbose_name = "Connecteurs (SMS, WhatsApp, Mobile Money, signature)"
    default_auto_field = "django.db.models.BigAutoField"
