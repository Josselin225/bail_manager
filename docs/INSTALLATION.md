# Installation de BailManager 2

Trois façons d'installer, selon le client :

- **A. Un poste unique** (petite agence) : SQLite, rien d'autre à installer que Python.
- **B. Serveur local en réseau** : un PC de l'agence sert les autres postes (comme la version PHP sous WAMP).
- **C. Cloud** : serveur Linux en ligne, accessible partout en HTTPS.

## A. Poste unique (Windows)

1. Installer Python 3.11+ depuis python.org en cochant **Add python.exe to PATH**.
2. Copier le dossier `bailmanager` sur le disque (ex. `C:\BailManager`).
3. Lancer `scripts-windows\1-installer.bat`. Il crée l'environnement Python, installe les composants, crée `backend\.env` avec une clé secrète, prépare la base et demande l'e-mail et le mot de passe de l'administrateur.
   - Si l'installation de `mysqlclient` échoue et que vous restez en SQLite, supprimez la ligne `mysqlclient` de `backend\requirements.txt` et relancez.
4. Lancer `scripts-windows\2-demarrer.bat` et ouvrir http://localhost:8000. Garder la fenêtre ouverte pendant l'utilisation.

Pour démarrer automatiquement à l'ouverture de session : placer un raccourci de `2-demarrer.bat` dans `shell:startup` (touche Windows + R).

## B. Serveur local en réseau

Sur le PC serveur :

1. Faire l'installation A.
2. **Base MySQL de WAMP (facultatif mais conseillé à partir de 3-4 postes)** : dans phpMyAdmin, créer la base `bailmanager` (interclassement `utf8mb4_unicode_ci`), puis dans `backend\.env` :
   ```
   DB_ENGINE=mysql
   DB_NAME=bailmanager
   DB_USER=root
   DB_PASS=
   ```
   et relancer `1-installer.bat` (il applique les tables sur MySQL).
3. Donner une **adresse IP fixe** au serveur (box/routeur ou paramètres réseau Windows), par ex. `192.168.1.10`.
4. Ouvrir le port dans le pare-feu (invite de commandes **en administrateur**) :
   ```
   netsh advfirewall firewall add rule name="BailManager" dir=in action=allow protocol=TCP localport=8000
   ```
5. Lancer `2-demarrer.bat` : la fenêtre affiche l'adresse réseau à utiliser.

Sur les autres postes : ouvrir `http://192.168.1.10:8000` dans Chrome/Edge et l'ajouter aux favoris. Rien à installer.

Le site vitrine et l'espace locataire sont à la même adresse (`/`, `/nos-maisons`, `/espace-locataire`). Pour qu'ils soient visibles depuis Internet, il faut l'installation C (ou une redirection de port sur la box, déconseillée sans HTTPS).

## C. Cloud (Ubuntu 22.04/24.04)

```bash
sudo apt update && sudo apt install -y python3-venv python3-dev build-essential pkg-config default-libmysqlclient-dev mariadb-server nginx certbot python3-certbot-nginx
sudo mysql -e "CREATE DATABASE bailmanager CHARACTER SET utf8mb4; CREATE USER 'bm'@'localhost' IDENTIFIED BY 'MOT_DE_PASSE'; GRANT ALL ON bailmanager.* TO 'bm'@'localhost';"
sudo mkdir -p /opt/bailmanager && sudo chown $USER /opt/bailmanager   # copier le dossier ici
cd /opt/bailmanager/backend && python3 -m venv .venv && . .venv/bin/activate
pip install -r requirements.txt gunicorn
cp .env.example .env    # DB_ENGINE=mysql, DB_USER=bm, DB_PASS=..., APP_URL=https://gestion.votre-agence.ci, HTTPS=1, SECRET_KEY=...
python manage.py migrate && python manage.py initialiser
```

Service systemd `/etc/systemd/system/bailmanager.service` :

```ini
[Unit]
Description=BailManager
After=network.target mariadb.service

[Service]
User=www-data
WorkingDirectory=/opt/bailmanager/backend
ExecStart=/opt/bailmanager/backend/.venv/bin/gunicorn config.wsgi:application --bind 127.0.0.1:8000 --workers 3 --timeout 120
Restart=always

[Install]
WantedBy=multi-user.target
```

Nginx `/etc/nginx/sites-available/bailmanager` :

```nginx
server {
    server_name gestion.votre-agence.ci;
    client_max_body_size 12M;
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo chown -R www-data /opt/bailmanager/backend
sudo ln -s /etc/nginx/sites-available/bailmanager /etc/nginx/sites-enabled/
sudo systemctl daemon-reload && sudo systemctl enable --now bailmanager && sudo systemctl reload nginx
sudo certbot --nginx -d gestion.votre-agence.ci
```

Dans `.env`, ajouter `CSRF_TRUSTED_ORIGINS=https://gestion.votre-agence.ci`.

## Tâches automatiques

| Tâche | Windows (Planificateur de tâches) | Linux (`crontab -e`, utilisateur www-data) |
|---|---|---|
| Rappels de loyer, chaque jour à 8 h | `scripts-windows\rappels-quotidiens.bat` | `0 8 * * * cd /opt/bailmanager/backend && .venv/bin/python manage.py envoyer_rappels` |
| Sauvegarde, chaque soir à 20 h | `scripts-windows\sauvegarde-quotidienne.bat` | `0 20 * * * cd /opt/bailmanager/backend && .venv/bin/python manage.py sauvegarder` |

Windows : Planificateur de tâches > Créer une tâche de base > Quotidienne > Démarrer un programme > choisir le `.bat`. Les sauvegardes vont dans `backend\sauvegardes\` (30 dernières conservées) : copiez-les régulièrement hors du PC.

Les rappels exigent la configuration e-mail (`MAIL_HOST`…). Sans elle, tout fonctionne sauf l'envoi des e-mails.

## E-mails (facultatif)

Exemple avec Gmail : activer la validation en deux étapes du compte, créer un « mot de passe d'application », puis :

```
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=agence@gmail.com
MAIL_PASS=xxxx xxxx xxxx xxxx
MAIL_FROM=Mon Agence <agence@gmail.com>
```

## Restaurer une sauvegarde

Sur une installation neuve (base vide, après `migrate`) :

```
python manage.py loaddata chemin\vers\donnees.json
```

puis copier le dossier `uploads` de l'archive dans `backend\uploads`.

## Mettre à jour le logiciel

Remplacer les dossiers `backend\apps`, `backend\config`, `backend\frontend_build` (et `frontend`), garder `backend\.env`, la base et `backend\uploads`, puis exécuter `python manage.py migrate` dans l'environnement.
