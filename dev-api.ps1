# Lance l'API KaarPro en développement avec PHP 8.3 de WAMP et le php.ini du projet (extensions PostgreSQL).
#   .\dev-api.ps1           -> PostgreSQL (lancer d'abord : docker compose up -d)
#   .\dev-api.ps1 -Sqlite   -> base SQLite locale, sans Docker
param([switch]$Sqlite, [switch]$Reinitialiser)

$racine = $PSScriptRoot
$env:PHPRC = Join-Path $racine '.php'
$env:PATH = 'C:\wamp64\bin\php\php8.3.14;' + $env:PATH
Set-Location (Join-Path $racine 'backend')

if ($Sqlite) {
    $base = Join-Path $racine 'backend\database\database.sqlite'
    if (-not (Test-Path $base)) { New-Item -ItemType File $base | Out-Null }
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = $base
}

if ($Reinitialiser) { php artisan migrate:fresh --seed --force } else { php artisan migrate --force; php artisan db:seed --force }
php artisan serve --host=127.0.0.1 --port=8000
