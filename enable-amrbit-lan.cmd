@echo off
title amrbit - Allow local network
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\enable-lan-firewall.ps1"
pause
