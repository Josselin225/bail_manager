@echo off
chcp 65001 >nul
title BailManager - Retrait du demarrage automatique
rem Annule 3-configurer-serveur.bat (a lancer en administrateur). Les donnees ne sont pas touchees.
net session >nul 2>&1 || (echo Lancez ce script en administrateur. & pause & exit /b 1)
schtasks /End /TN "BailManager\Serveur" >nul 2>&1
for %%T in ("BailManager\Serveur" "BailManager\Rappels" "BailManager\Sauvegarde midi" "BailManager\Sauvegarde soir") do schtasks /Delete /F /TN %%T >nul 2>&1
netsh advfirewall firewall delete rule name="BailManager" >nul 2>&1
echo Demarrage automatique, taches quotidiennes et regle de pare-feu retires.
echo Pour utiliser BailManager, relancez 2-demarrer.bat.
pause
