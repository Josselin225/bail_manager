@echo off
rem A planifier chaque jour (Planificateur de taches Windows), voir docs\INSTALLATION.md
cd /d "%~dp0..\backend"
call .venv\Scripts\activate.bat
python manage.py envoyer_rappels >> journal-taches.log 2>&1
