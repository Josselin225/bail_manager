@echo off
chcp 65001 >nul
title BailManager - Installation
cd /d "%~dp0..\backend"
echo.
echo  === Installation de BailManager 2 ===
echo.
where python >nul 2>nul || (echo Python est introuvable. Installez Python 3.11+ depuis python.org en cochant "Add python.exe to PATH". & pause & exit /b 1)
if not exist ".venv" (
  echo Creation de l'environnement Python...
  python -m venv .venv || (pause & exit /b 1)
)
call .venv\Scripts\activate.bat
echo Installation des composants (connexion Internet requise, quelques minutes)...
python -m pip install --upgrade pip >nul
pip install -r requirements.txt || (echo. & echo Echec de l'installation. Si l'erreur concerne mysqlclient et que vous utilisez SQLite, retirez la ligne mysqlclient de requirements.txt puis relancez. & pause & exit /b 1)
if not exist ".env" (
  copy .env.example .env >nul
  python -c "import secrets,re;p='.env';s=open(p,encoding='utf-8').read();s=s.replace('remplacez-moi-par-une-longue-chaine-aleatoire',secrets.token_urlsafe(50));open(p,'w',encoding='utf-8').write(s)"
  echo Fichier .env cree. Ouvrez-le pour choisir SQLite ou MySQL avant de continuer si besoin.
)
echo Preparation de la base de donnees...
python manage.py migrate || (pause & exit /b 1)
echo.
echo Creation du compte administrateur :
python manage.py initialiser
echo.
echo  Installation terminee. Lancez "2-demarrer.bat".
pause
