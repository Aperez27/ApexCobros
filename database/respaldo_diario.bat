@echo off
REM APEX - Respaldo diario de la base de datos.
REM Programar en el Programador de tareas de Windows (ver Manual de instalacion, capitulo Respaldos).
REM Requiere que MySQL de XAMPP este encendido a la hora programada.
cd /d "%~dp0.."
"C:\xampp\php\php.exe" database\respaldo.php --dias=30 >> storage\logs\respaldos.log 2>&1
