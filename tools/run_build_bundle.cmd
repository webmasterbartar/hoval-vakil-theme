@echo off
REM ساخت بسته برای wp-content/hovalvakil-import — از ریشهٔ تم اجرا شود.
cd /d "%~dp0.."
python tools\build_import_bundle.py %*
exit /b %ERRORLEVEL%
