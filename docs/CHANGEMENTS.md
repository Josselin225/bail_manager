# Version 2 — ce qui change par rapport à la version PHP

## Règles de gestion conservées

- Un bailleur ne peut recevoir ni bien ni bail sans **mandat de gestion en vigueur** ; un nouveau mandat résilie le précédent (renouvellement).
- Caution fixée à **2 mois de loyer** ; avance et frais d'agence pré-remplis et modifiables.
- **Interdiction d'encaisser un mois tant qu'un mois antérieur n'est pas soldé** ; paiement partiel accepté.
- Chaque encaissement : quittance, crédit net au compte du bailleur, commission en caisse agence.
- Solde bailleur = somme de l'historique du compte courant (source de vérité unique).
- Retenues sur caution : réparations → caisse agence ; loyers → bailleur. Résiliation : bien de nouveau disponible.
- Rôles admin / agent, expiration de session par inactivité réglable par utilisateur, anti brute-force persistant, journal d'audit, CSRF, contrôle du type réel des fichiers envoyés (8 Mo).
- Codes bailleur `BA-XX<année>-<ordre>`, références de reçus `REC-XXXXXX`, articles de la loi n° 2019-576.

## Règles corrigées ou complétées

| Sujet | Version 1 (PHP) | Version 2 |
|---|---|---|
| Contrôle des dettes antérieures | Vérifiait de janvier au mois choisi, même pour un bail commencé en cours d'année, et seulement dans l'année | Vérifie depuis le début du bail, sur plusieurs années |
| Taux de commission | Taux global des paramètres, même si le mandat prévoyait un autre taux | Taux du mandat du bailleur ; taux des paramètres à défaut |
| Avance de loyer | Encaissée mais comptabilisée nulle part ; échéance calculée sur « condition / 2 » | Génère les quittances des premiers mois avec la répartition bailleur / commission ; l'échéance en découle |
| Frais d'agence | Non comptabilisés | Entrée « Droit d'agence » en caisse |
| Surpaiement d'un mois | Accepté ; pouvait avancer l'échéance deux fois | Refusé avec le reste exact à payer |
| Prochaine échéance | Incrémentée de +1 mois à chaque paiement complet | Recalculée à partir des paiements réels (premier mois non soldé) |
| Révision de loyer | Tous les mois passés comparés au nouveau loyer (faux impayés) | Date d'effet : les mois antérieurs gardent l'ancien loyer |
| Retenue « loyers » sur caution | Crédit au bailleur sans commission ni quittance | Imputée sur les mois impayés les plus anciens, avec quittance et commission |
| Cautions | Suivies par locataire (mélange possible entre deux baux) | Suivies par contrat |
| Versement au bailleur, retrait de caisse | Sans plafond | Impossible au-delà du solde disponible |
| Bail sur une maison déjà occupée | Non bloqué | Bloqué |
| Avance > 2 mois | Possible | Refusée (Art. 415) |
| Suppression d'un locataire | Testait un statut « résilié » inexistant | Impossible s'il a (eu) un bail ; pièces supprimées avec la fiche |
| Statuts de réservation | Incohérents (`termine`, `confirmee` hors liste) | En attente, Confirmée, Visite effectuée, Annulée |
| Quittances par e-mail | Envoyées à l'agence (e-mail locataire non stocké) | Envoyées au locataire (nouveau champ e-mail) et à l'agence |
| Données sensibles (CNI, scans) | Dossier `uploads` accessible par URL | Servies uniquement au personnel connecté |
| QR code des quittances | Généré par un service en ligne | Généré localement (fonctionne hors connexion) |
| Tables `paiements`, `loyers_generes`, `versements_bailleurs`, colonne `total_commissions_entreprises` | Jamais alimentées | Supprimées ; commissions calculées depuis la caisse |

## Nouveautés

- Échéancier visuel du mois au tableau de bord et échéancier mois par mois sur chaque contrat.
- Recherche globale Ctrl + K (y compris par numéro de reçu), mode sombre, couleur du menu, interface utilisable sur téléphone.
- Fiches complètes bailleur / maison / locataire / contrat, avec documents rattachés.
- Quittance avec souche agence et montant en lettres ; reçu de signature ; reçu de résiliation ; relevé de caution ; mandat imprimable avec la liste des biens confiés.
- Exports Excel sur toutes les listes et tous les rapports ; rapport sur période libre.
- Calendrier avec l'état de chaque loyer (payé, partiel, en retard, à venir) et les fins de mandat.
- Purge du journal limitée aux entrées de plus de 90 jours.
- Import automatique de la base v1 (mots de passe conservés).

## Correspondance des pages

| Version 1 | Version 2 |
|---|---|
| `index.php`, `nos_maisons.php`, `services.php`, `contact.php` | `/`, `/nos-maisons`, `/services`, `/contact` |
| `login.php`, `forgot_password.php`, `reset_password_step2.php` | `/connexion`, `/mot-de-passe-oublie`, `/reinitialiser/...` |
| `locataire_portail.php`, `locataire_espace.php` | `/espace-locataire` |
| `dashboard.php` | `/tableau-de-bord` |
| `bailleurs.php`, `voir_bailleur.php`, `recu_mandat.php` | `/bailleurs`, `/bailleurs/:id`, `/impression/mandat/:id` |
| `maisons.php` / `locataires.php` | `/maisons`, `/maisons/:id` / `/locataires`, `/locataires/:id` |
| `contrats.php`, `recu_contrat.php`, `voir_recu_resiliation.php` | `/contrats`, `/contrats/nouveau`, `/contrats/:id`, `/impression/contrat/:id`, `/impression/recu-contrat/:id`, `/impression/resiliation/:id` |
| `encaissements.php`, `liste_encaissements.php`, `historique_paiements.php`, `quittance.php` | `/encaissements` (onglets), fiche contrat, `/impression/quittance/:id` |
| `calendrier.php` | `/calendrier` |
| `charges_locatives.php`, `revisions_loyer.php` | `/charges`, `/revisions` |
| `compte_bailleur.php`, `print_releve_bailleur.php` | `/comptes-bailleurs`, `/impression/releve-bailleur/:id` |
| `caisse_entreprise.php`, `rapport_caisse.php` | `/caisse`, rapports |
| `gestion_cautions.php`, `releve_caution.php`, `rapport_cautions.php` | `/cautions`, `/impression/releve-caution/:id` |
| `liste_reservations.php`, `liste_messages.php`, `messages_locataires.php` | `/reservations`, `/messages`, `/messagerie` |
| `rapports.php`, `gen_rapport_*.php`, `gen_etat_occupation.php`, `gen_liste_impayes.php`, `rapport_encaissements.php` | `/rapports` puis `/impression/rapport/...` |
| `journal_activites.php`, `gestion_users.php`, `settings.php`, `profil.php`, `documents.php` | `/journal`, `/utilisateurs`, `/parametres`, `/profil`, onglet Documents des fiches |
| `guide.php`, `a_propos.php` | `/guide`, `/a-propos` |
| `cron_rappels.php`, `cron_backup.php`, `backup.php` | `manage.py envoyer_rappels`, `manage.py sauvegarder`, Paramètres > Sauvegarde |

## À noter

- Un reçu émis ne peut être ni modifié ni supprimé (traçabilité). En cas d'erreur de saisie, il faudra une fonction d'annulation par contre-passation : non incluse dans cette version.
- Les documents s'enregistrent en PDF via « Imprimer » du navigateur (destination « Enregistrer au format PDF »).
