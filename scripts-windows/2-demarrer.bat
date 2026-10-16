@echo off
chcp 65001 >nul
title BailManager - Serveur (ne pas fermer)
cd /d "%~dp0..\backend"
call .venv\Scripts\activate.bat
python serveur.py --port 8000
pause
