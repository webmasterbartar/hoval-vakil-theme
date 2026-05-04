@echo off
REM Run from repo: double-click or: tools\run_import.cmd
cd /d "%~dp0.."
python "%~dp0import_lawyers.py" %*
if errorlevel 1 exit /b %errorlevel%
