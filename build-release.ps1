<#
.SYNOPSIS
    Baut lokal ein installierbares Plugin-ZIP -- dieselben Schritte wie der
    GitHub-Workflow (.github/workflows/release.yml), nur ohne Tag/Release.

.DESCRIPTION
    Reproduziert die Build-Schritte des Release-Workflows:
      1. Sauberen Staging-Ordner aus dem aktuellen Arbeitsstand erzeugen
         (Dev-/VCS-Dateien werden ausgeschlossen, Quellbaum bleibt unberuehrt).
      2. Composer-Autoloader optimiert / ohne Dev-Pakete neu aufbauen.
      3. Alle public/js/*.js mit Terser zu *.min.js minifizieren.
      4. $loadMinified = false; -> true; setzen (Produktionsmodus).
      5. Alles als flaches wp-sdtrk.zip packen (Dateien direkt im ZIP-Root,
         KEIN Wrapper-Ordner -- wie der Workflow),
         direkt installierbar via WordPress > Plugins > Installieren > Hochladen.

    Bewusste Abweichungen vom Workflow (lokal sinnvoller):
      * Baut aus dem ARBEITSSTAND (inkl. nicht committeter Aenderungen),
        nicht aus einem Git-Tag -- schnelleres Iterieren beim Live-Testen.
      * Der Quellbaum wird NICHT veraendert (keine .min.js im Repo, der
        $loadMinified-Schalter bleibt auf false). Alles passiert im Staging.
      * Das ZIP ist flach (keine Wrapper-Ordner), passend zum Upload-/Update-Pfad.
      * Reine Dev-Ordner (spec, tasks, .claude, .vscode, tests ...) werden
        ausgeschlossen -- schlankeres Plugin-Paket.

.PARAMETER OutDir
    Zielordner fuer das ZIP. Standard: ./release

.PARAMETER FreshComposer
    Statt nur den Autoloader neu zu bauen, ein volles
    'composer install --no-dev --optimize-autoloader' im Staging ausfuehren
    (braucht Netzwerk; erzwingt ein sauberes vendor/).

.PARAMETER KeepStaging
    Staging-Ordner nach dem Build nicht loeschen (zum Debuggen).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\build-release.ps1
    # -> release/wp-sdtrk.zip

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\build-release.ps1 -FreshComposer
#>
[CmdletBinding()]
param(
    [string]$OutDir,
    [switch]$FreshComposer,
    [switch]$KeepStaging
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Slug = 'wp-sdtrk'
$Root = $PSScriptRoot
if (-not $OutDir) { $OutDir = Join-Path $Root 'release' }

function Write-Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Write-Ok($msg)   { Write-Host "    OK  $msg" -ForegroundColor Green }
function Fail($msg)       { Write-Host "    ERR $msg" -ForegroundColor Red; exit 1 }

# --- Voraussetzungen pruefen -------------------------------------------------
Write-Step 'Pruefe benoetigte Werkzeuge'
foreach ($tool in 'composer', 'node', 'npx') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        Fail "'$tool' nicht im PATH gefunden."
    }
    Write-Ok "$tool gefunden"
}

# --- Version aus dem Plugin-Header lesen -------------------------------------
$bootstrap = Join-Path $Root 'wp-sdtrk.php'
if (-not (Test-Path $bootstrap)) { Fail 'wp-sdtrk.php nicht gefunden -- falsches Verzeichnis?' }
$verMatch = Select-String -Path $bootstrap -Pattern 'Version:\s*([0-9][^\s]*)' | Select-Object -First 1
$Version = if ($verMatch) { $verMatch.Matches[0].Groups[1].Value } else { '0.0.0' }
Write-Ok "Plugin-Version: $Version"

# --- Staging vorbereiten -----------------------------------------------------
$StageParent = Join-Path ([System.IO.Path]::GetTempPath()) ("{0}-build" -f $Slug)
$Stage       = Join-Path $StageParent $Slug   # Inhalt liegt unter .../wp-sdtrk/
Write-Step "Staging-Ordner anlegen: $Stage"
if (Test-Path $StageParent) { Remove-Item $StageParent -Recurse -Force }
New-Item -ItemType Directory -Path $Stage -Force | Out-Null

# Ordner, die NICHT ins Paket gehoeren (Root-genau via absolute Pfade).
$excludeDirs = @(
    '.git', '.github', '.claude', '.vscode', '.idea',
    'node_modules', 'tests', 'spec', 'tasks', 'release', 'temp'
) | ForEach-Object { Join-Path $Root $_ }

# Dateien, die NICHT ins Paket gehoeren.
$excludeFiles = @(
    'CLAUDE.md', 'SPEC.md', 'TODO.md', 'README.md',
    '.gitignore', '.gitattributes',
    'phpunit.xml', 'phpunit.xml.dist',
    'build-release.ps1'
)

Write-Step 'Arbeitsstand nach Staging kopieren (Dev-/VCS-Dateien ausgeschlossen)'
$rcArgs = @($Root, $Stage, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/R:1', '/W:1')
$rcArgs += '/XD'; $rcArgs += $excludeDirs
$rcArgs += '/XF'; $rcArgs += $excludeFiles
& robocopy @rcArgs | Out-Null
# robocopy: Exit-Codes 0 bis 7 = Erfolg, ab 8 = Fehler.
if ($LASTEXITCODE -ge 8) { Fail "robocopy fehlgeschlagen (Code $LASTEXITCODE)" }
$global:LASTEXITCODE = 0
Write-Ok 'Dateien kopiert'

# --- Composer-Autoloader (no-dev, optimiert) ---------------------------------
if (Test-Path (Join-Path $Stage 'composer.json')) {
    if ($FreshComposer) {
        Write-Step 'composer install --no-dev --optimize-autoloader (Netzwerk)'
        Push-Location $Stage
        try {
            if (Test-Path 'vendor') { Remove-Item 'vendor' -Recurse -Force }
            & composer install --no-dev --optimize-autoloader --no-interaction
            if ($LASTEXITCODE -ne 0) { Fail 'composer install fehlgeschlagen' }
        } finally { Pop-Location }
    } else {
        Write-Step 'composer dump-autoload --no-dev --optimize (offline)'
        Push-Location $Stage
        try {
            & composer dump-autoload --no-dev --optimize --no-interaction
            if ($LASTEXITCODE -ne 0) {
                Write-Host '    WARN  dump-autoload fehlgeschlagen -- nutze kopiertes vendor/ unveraendert.' -ForegroundColor Yellow
            }
        } finally { Pop-Location }
    }
    Write-Ok 'Autoloader bereit'
} else {
    Write-Host '    WARN  keine composer.json im Staging -- Composer-Schritt uebersprungen.' -ForegroundColor Yellow
}

# --- JavaScript minifizieren -------------------------------------------------
Write-Step 'JavaScript minifizieren (Terser)'
$jsDir = Join-Path $Stage 'public\js'
if (-not (Test-Path $jsDir)) { Fail 'public/js nicht gefunden im Staging' }
$jsFiles = Get-ChildItem $jsDir -Filter '*.js' -File | Where-Object { $_.Name -notlike '*.min.js' }
if (-not $jsFiles) { Fail 'keine .js-Dateien zum Minifizieren gefunden' }
foreach ($js in $jsFiles) {
    # foo.js -> foo.min.js
    $min = Join-Path $js.DirectoryName ($js.BaseName + '.min.js')
    & npx --yes terser $js.FullName --compress --mangle --comments false --output $min
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $min)) { Fail "Minify fehlgeschlagen: $($js.Name)" }
    Write-Ok "$($js.Name) -> $(Split-Path $min -Leaf)"
}

# --- Produktionsmodus aktivieren ($loadMinified = true) ----------------------
Write-Step 'Produktionsmodus aktivieren ($loadMinified = true)'
$publicClass = Join-Path $Stage 'public\class-wp-sdtrk-public.php'
if (-not (Test-Path $publicClass)) { Fail 'class-wp-sdtrk-public.php nicht gefunden' }
$content = Get-Content $publicClass -Raw
$content = $content.Replace('$loadMinified = false;', '$loadMinified = true;')
if ($content -notmatch '\$loadMinified = true;') { Fail 'Konnte $loadMinified nicht auf true setzen' }
# WICHTIG: BOM-frei schreiben. In Windows PowerShell 5.1 erzeugt
# `Set-Content -Encoding UTF8` ein UTF-8-BOM (EF BB BF), das vor `<?php`
# landet und auf dem Live-Host "headers already sent" ausloest. UTF8Encoding
# mit $false unterdrueckt das BOM.
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($publicClass, $content, $utf8NoBom)
Write-Ok 'Produktionsmodus gesetzt'

# --- Composer-Metadaten aus dem Paket entfernen ------------------------------
Write-Step 'Composer-Metadaten aus dem Paket entfernen'
foreach ($f in 'composer.json', 'composer.lock') {
    $p = Join-Path $Stage $f
    if (Test-Path $p) { Remove-Item $p -Force }
}
Write-Ok 'aufgeraeumt'

# --- ZIP packen --------------------------------------------------------------
# Hinweis: NICHT Compress-Archive verwenden -- es schreibt unter Windows
# PowerShell 5.1 Backslashes als Pfadtrenner in die ZIP-Eintraege, was beim
# Entpacken auf Linux-WordPress-Hosts den Verzeichnisbaum zerstoert. Wir bauen
# die Eintraege daher manuell mit erzwungenen Forward-Slashes.
Write-Step 'ZIP erstellen'
if (-not (Test-Path $OutDir)) { New-Item -ItemType Directory -Path $OutDir -Force | Out-Null }
$zipPath = Join-Path $OutDir "$Slug.zip"
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$prefixLen = $Stage.TrimEnd('\').Length + 1   # FLACH: Eintraege relativ zur Staging-Wurzel, kein Wrapper-Ordner
$zipStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::Create)
try {
    $archive = New-Object System.IO.Compression.ZipArchive($zipStream, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        Get-ChildItem $Stage -Recurse -File | ForEach-Object {
            $rel = $_.FullName.Substring($prefixLen).Replace('\', '/')
            $entry = $archive.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
            $es = $entry.Open()
            try {
                $fs = [System.IO.File]::OpenRead($_.FullName)
                try { $fs.CopyTo($es) } finally { $fs.Dispose() }
            } finally { $es.Dispose() }
        }
    } finally { $archive.Dispose() }
} finally { $zipStream.Dispose() }
Write-Ok "ZIP: $zipPath"

# --- Staging aufraeumen ------------------------------------------------------
if (-not $KeepStaging) {
    Remove-Item $StageParent -Recurse -Force
} else {
    Write-Host "    Staging behalten: $Stage" -ForegroundColor Yellow
}

$sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 2)
Write-Host "`nFertig: $zipPath ($sizeMB MB) -- Version $Version" -ForegroundColor Green
Write-Host 'Hochladen via WordPress: Plugins > Installieren > Plugin hochladen.' -ForegroundColor Green
