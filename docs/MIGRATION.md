# Passer d'une installation PHP (v1) à BailManager 2

L'import reprend **toutes les données** : paramètres, clauses, utilisateurs, bailleurs, mandats, maisons, locataires, codes d'accès locataires, documents, contrats, encaissements, comptes bailleurs, caisse, cautions, charges, révisions, réservations, messages et journal. Les identifiants (id) sont conservés.

**Les mots de passe restent valables** : les empreintes bcrypt de PHP sont reconnues et converties automatiquement à la première connexion. Les codes d'accès des locataires aussi.

## Étapes

1. **Sauvegarder** l'ancienne base (phpMyAdmin > Exporter) et le dossier `bail_manager\uploads`.
2. Installer BailManager 2 (docs/INSTALLATION.md) **sans créer d'administrateur** : à la question de l'e-mail, laissez vide et validez — l'administrateur sera importé.
3. Dans `backend\.env`, indiquer l'ancienne base :
   ```
   LEGACY_DB_NAME=gestion_bail
   LEGACY_DB_USER=root
   LEGACY_DB_PASS=
   LEGACY_DB_HOST=localhost
   ```
4. Dans une invite de commandes :
   ```
   cd C:\BailManager\backend
   .venv\Scripts\activate
   python manage.py importer_ancienne_base --uploads "C:\wamp64\www\bail_manager\uploads"
   ```
   L'option `--uploads` copie photos, CNI, scans de mandats et documents.
5. Lancer `2-demarrer.bat` et se connecter avec les identifiants habituels.
6. Vérifier quelques soldes bailleurs, cautions et prochaines échéances, puis retirer les lignes `LEGACY_...` du `.env`.

La base cible doit être vide : la commande refuse sinon (`--forcer` pour passer outre, à vos risques).

## Points d'attention

- **Prochaines échéances** : elles sont recalculées d'après les paiements réellement enregistrés. La commande indique combien de contrats ont changé d'échéance — ils méritent un coup d'œil.
- **Commissions des anciens encaissements** : la v1 ne stockait pas la commission sur l'encaissement ; elle est recalculée au taux par défaut des paramètres pour l'affichage. Les montants déjà inscrits dans la caisse et les comptes bailleurs sont importés tels quels.
- **Avances des anciens contrats** : en v1, l'avance de loyer n'était pas enregistrée comme encaissement. L'échéancier de ces contrats commence donc au premier mois réellement payé.
- Les tables jamais alimentées en v1 (`paiements`, `loyers_generes`, `versements_bailleurs`, `sessions`) ne sont pas reprises.
