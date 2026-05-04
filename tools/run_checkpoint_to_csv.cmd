@echo off
cd /d "%~dp0.."
python tools\checkpoint_to_csv.py %*
exit /b %ERRORLEVEL%
