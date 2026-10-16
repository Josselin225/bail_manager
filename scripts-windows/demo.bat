@echo off
chcp 65001 >nul
rem Remplit une base VIDE avec des donnees fictives (pour presenter le logiciel a un client)
cd /d "%~dp0..\backend"
call .venv\Scripts\activate.bat
python manage.py donnees_demo
pause
