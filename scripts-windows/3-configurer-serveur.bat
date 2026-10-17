@echo off
chcp 65001 >nul
title BailManager - Configuration du serveur de l'agence
rem A lancer UNE FOIS sur le PC serveur, clic droit > "Executer en tant qu'administrateur".
rem  - BailManager demarre tout seul avec Windows (meme sans ouvrir de session) ;
rem  - le pare-feu laisse passer les autres postes du reseau ;
rem  - rappels (8 h) et sauvegarde (13 h et 20 h) se lancent chaque jour.

net session >nul 2>&1 || (echo. & echo  Ce script doit etre lance en administrateur : clic droit ^> "Executer en tant qu'administrateur". & echo. & pause & exit /b 1)

set "BACKEND=%~dp0..\backend"
for %%I in ("%BACKEND%") do set "BACKEND=%%~fI"
set "SCRIPTS=%~dp0"
set "PORT=8000"
if not exist "%BACKEND%\.venv\Scripts\python.exe" (echo Lancez d'abord 1-installer.bat. & pause & exit /b 1)

echo.
echo  === Configuration du serveur BailManager ===
echo.
echo [1/4] Pare-feu : ouverture du port %PORT% aux postes du reseau local...
netsh advfirewall firewall delete rule name="BailManager" >nul 2>&1
netsh advfirewall firewall add rule name="BailManager" dir=in action=allow protocol=TCP localport=%PORT% profile=private,domain >nul || goto erreur

echo [2/4] Demarrage automatique avec Windows...
schtasks /Create /F /TN "BailManager\Serveur" /SC ONSTART /RU SYSTEM /RL HIGHEST /DELAY 0000:30 ^
  /TR "\"%BACKEND%\.venv\Scripts\python.exe\" \"%BACKEND%\serveur.py\" --port %PORT%" >nul || goto erreur

echo [3/4] Taches quotidiennes (rappels 8 h, sauvegardes 13 h et 20 h)...
schtasks /Create /F /TN "BailManager\Rappels" /SC DAILY /ST 08:00 /RU SYSTEM /TR "\"%SCRIPTS%rappels-quotidiens.bat\"" >nul || goto erreur
schtasks /Create /F /TN "BailManager\Sauvegarde midi" /SC DAILY /ST 13:00 /RU SYSTEM /TR "\"%SCRIPTS%sauvegarde-quotidienne.bat\"" >nul || goto erreur
schtasks /Create /F /TN "BailManager\Sauvegarde soir" /SC DAILY /ST 20:00 /RU SYSTEM /TR "\"%SCRIPTS%sauvegarde-quotidienne.bat\"" >nul || goto erreur

echo [4/4] Demarrage du serveur...
schtasks /Run /TN "BailManager\Serveur" >nul
timeout /t 5 /nobreak >nul

echo.
echo  Configuration terminee. BailManager tourne en arriere-plan et redemarrera avec Windows.
echo  N'utilisez plus 2-demarrer.bat sur ce PC (le port serait deja pris).
echo.
echo  Adresse a ouvrir sur les autres postes et telephones du reseau :
for /f "usebackq delims=" %%A in (`"%BACKEND%\.venv\Scripts\python.exe" -c "import socket;s=socket.socket(socket.AF_INET,socket.SOCK_DGRAM);s.connect(('8.8.8.8',80));print(s.getsockname()[0])"`) do echo     http://%%A:%PORT%
echo.
echo  Conseil : donnez une adresse IP fixe a ce PC (box ou routeur) pour que cette adresse ne change pas.
echo.
pause
exit /b 0

:erreur
echo.
echo  Une etape a echoue. Verifiez que le script est lance en administrateur, puis recommencez.
pause
exit /b 1
