@echo off
rem ----------------------------------------------------------------
rem  Kitsune Notes - arranque rapido en Windows
rem  Doble clic en este fichero (o ejecutalo desde PowerShell).
rem  Requiere PHP 8.1 o superior en el PATH (README, apartado 3).
rem  Pensado para la base de datos SQLite por defecto.
rem ----------------------------------------------------------------
setlocal
chcp 65001 >nul
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo.
    echo [!] No se encuentra PHP en el PATH.
    echo     Instalalo siguiendo el apartado "Windows" del README.md
    echo     y vuelve a abrir esta ventana.
    echo.
    pause
    exit /b 1
)

if not exist ".env" (
    copy ".env.example" ".env" >nul
    echo Creado el fichero .env a partir de .env.example
)

if not exist "storage\database\kitsune.sqlite" (
    echo Creando la base de datos con los datos de prueba...
    php bin\install.php
    if errorlevel 1 (
        echo.
        echo [!] La instalacion ha fallado. Lee el mensaje de arriba.
        pause
        exit /b 1
    )
)

echo.
echo  Kitsune Notes en marcha:   http://localhost:8000
echo  Back-office:               http://localhost:8000/admin/login
echo  Para detener el servidor:  Ctrl + C
echo.
php -S localhost:8000 -t public
pause
