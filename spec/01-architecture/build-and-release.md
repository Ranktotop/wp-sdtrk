# 01 — Build & Release (CI/CD)

Der Release-Build wird über **GitHub Actions** automatisiert. Die Pipeline erzeugt aus dem Repository ein installierbares Plugin-ZIP und hängt es als Asset an einen GitHub-Release. Dieses Asset ist die Quelle, die der [Plugin-Update-Checker](../06-integrations.md#plugin-update-checker) im WordPress-Dashboard ausliefert.

- **Datei:** `.github/workflows/release.yml`
- **Workflow-Name:** `Build & Release Plugin`
- **Runner:** `ubuntu-latest`

## Auslöser

Push eines Tags nach dem Muster `v*` (z. B. `v1.7.6`):

```yaml
on:
  push:
    tags:
      - 'v*'
```

Der Tag-Name (`github.ref_name`) wird unverändert als Release-Tag **und** Release-Name verwendet. Es gibt keinen separaten Branch-Trigger und keinen manuellen Dispatch.

## Schritte

| # | Schritt | Wirkung |
|---|---------|---------|
| 1 | **Checkout** (`actions/checkout@v5`) | Repository auschecken |
| 2 | **PHP & Composer** (`shivammathur/setup-php@v2`) | PHP **8.1** bereitstellen |
| 3 | **Composer install** | `composer install --no-dev --optimize-autoloader` — nur Produktiv-Abhängigkeiten, optimierter Autoloader |
| 4 | **Node.js** (`actions/setup-node@v5`) | Node **20** bereitstellen |
| 5 | **Terser** | `npm install -g terser` (JS-Minifier) |
| 6 | **JS-Minify** | siehe [Minifizierung](#minifizierung) |
| 7 | **Prod-Switch** | siehe [`$loadMinified`-Umschaltung](#loadminified-umschaltung) |
| 8 | **Dev-Dateien entfernen** | siehe [Paketierung](#paketierung) |
| 9 | **ZIP erstellen** | siehe [Paketierung](#paketierung) |
| 10 | **GitHub Release** (`softprops/action-gh-release@v2`) | siehe [Release](#release) |

### Minifizierung

Alle `*.js`-Dateien unter `public/js/` (außer bereits minifizierte `*.min.js`) werden mit Terser zu `*.min.js` neben der Quelldatei kompiliert:

```bash
terser "$jsfile" --compress --mangle --output "$minfile" --comments false
```

Fehlt das Verzeichnis `public/js` oder schlägt eine Minifizierung fehl, bricht der Job mit `exit 1` ab. Die `.min.js`-Dateien werden **nur im Release** erzeugt; im Repository liegen sie nicht (committet wird ausschließlich die unminifizierte Quelle).

### `$loadMinified`-Umschaltung

Im Code steht der Schalter auf `false` (in `public/class-wp-sdtrk-public.php`), sodass in der Entwicklung die unminifizierten Skripte geladen werden:

```php
$loadMinified = false;
$minifySwitch = ($loadMinified) ? ".min" : "";
```

Der Release-Build flippt diesen Wert per `sed` auf `true`, sodass das ausgelieferte Plugin die `.min.js`-Varianten lädt:

```bash
sed -i 's/\$loadMinified = false;/\$loadMinified = true;/g' public/class-wp-sdtrk-public.php
```

Schlägt die Ersetzung fehl (Datei fehlt oder Muster nicht gefunden), bricht der Job ab. Diese Änderung passiert **nur im CI-Build** und wird nicht ins Repository zurückgeschrieben.

### Paketierung

Vor dem Zippen entfernt ein eigener Schritt reine Entwicklungs-Artefakte hart aus dem Checkout, damit sie **nie** im Paket landen (auch nicht als leere Ordner-Einträge):

```bash
rm -rf spec .claude tasks tests .vscode .idea CLAUDE.md
```

Anschließend wird gepackt — die `-x`-Ausschlüsse dienen als zweite Absicherung:

```bash
mkdir -p release
zip -r release/wp-sdtrk.zip . \
  -x ".git*" ".github/*" "temp/*" "tests/*" "*.md" "composer.*" "phpunit.*" "node_modules/*" \
     "spec/*" ".claude/*" "tasks/*" ".vscode/*" ".idea/*" "release/*"
```

Ausgeschlossen werden Versionskontrolle/CI (`.git*`, `.github/*`), Dev-Konfiguration (`.claude/*`, `.vscode/*`, `.idea/*`), Spezifikation und Aufgaben (`spec/*`, `tasks/*`), temporäre und Test-Artefakte (`temp/*`, `tests/*`, `phpunit.*`), Dokumentation (`*.md`, inkl. `CLAUDE.md`), Composer-Manifeste (`composer.*`), `node_modules/*` und das Build-Ausgabeverzeichnis (`release/*`). Der bereits installierte `vendor/`-Ordner (Redux, Update-Checker) **bleibt** im ZIP enthalten.

Dieselben Ausschlüsse sind zusätzlich in `.gitattributes` als `export-ignore` hinterlegt, sodass auch `git archive` `spec/`, `.claude/`, `tasks/`, `.vscode/`, `.idea/` und `CLAUDE.md` nie exportiert.

### Release

```yaml
uses: softprops/action-gh-release@v2
with:
  tag_name: ${{ github.ref_name }}
  name:     ${{ github.ref_name }}
  files:    release/wp-sdtrk.zip
  body: |
    ## Release ${{ github.ref_name }}
    ...
env:
  GITHUB_TOKEN: ${{ secrets.GH_RELEASE_TOKEN }}
```

- Das ZIP wird als **Release-Asset** angehängt — exakt die Datei, die [`enableReleaseAssets()`](../06-integrations.md#plugin-update-checker) erwartet.
- Authentifizierung über das Repository-Secret **`GH_RELEASE_TOKEN`** (nicht der Standard-`GITHUB_TOKEN`).
- Der Release-Body ist ein statischer Template-Text (Überschrift mit `github.ref_name` plus feste „Changes"-Stichpunkte) — kein generiertes Changelog.

## Lokaler Build (`build-release.ps1`)

Für Tests auf einer Live-Seite ohne Tag/Release liegt im Projekt-Root das PowerShell-Skript **`build-release.ps1`**. Es erzeugt dasselbe installierbare ZIP wie die Pipeline (`release/wp-sdtrk.zip`), führt aber keinen GitHub-Release durch.

```powershell
powershell -ExecutionPolicy Bypass -File .\build-release.ps1            # Standard
powershell -ExecutionPolicy Bypass -File .\build-release.ps1 -FreshComposer  # vendor/ neu installieren (Netzwerk)
```

Es reproduziert die Wirkung der CI-Schritte 3, 6, 7, 8 (Composer-Autoloader ohne Dev-Pakete/optimiert, Terser-Minify, `$loadMinified`-Flip, ZIP), mit folgenden bewussten Abweichungen für den lokalen Einsatz:

- **Quelle:** baut aus dem aktuellen **Arbeitsstand** (inkl. nicht committeter Änderungen), nicht aus einem Git-Tag.
- **Quellbaum unberührt:** Minify, `$loadMinified=true` und Aufräumen passieren ausschließlich in einem temporären Staging-Ordner; im Repository bleiben weder `.min.js` noch der geflippte Schalter zurück.
- **Composer:** standardmäßig nur `composer dump-autoload --no-dev --optimize` (offline, nutzt vorhandenes `vendor/`); `-FreshComposer` erzwingt ein volles `composer install --no-dev --optimize-autoloader`.
- **ZIP-Struktur:** **flach** — Dateien liegen direkt im ZIP-Root (kein Wrapper-Ordner), identisch zur CI-Paketierung. Erzwungene Forward-Slash-Pfadtrenner (WordPress-/Linux-kompatibel; `Compress-Archive` würde unter Windows PowerShell 5.1 Backslashes schreiben, daher manueller ZIP-Aufbau).
- **Ausschlüsse:** zusätzlich zu den CI-Ausschlüssen werden reine Dev-Ordner entfernt (`spec/`, `tasks/`, `.claude/`, `.vscode/`, `.idea/`).
- **BOM-frei:** Der `$loadMinified`-Flip schreibt `class-wp-sdtrk-public.php` über `System.Text.UTF8Encoding($false)` zurück (nicht `Set-Content -Encoding UTF8`), damit kein UTF-8-BOM vor `<?php` gerät. Ein BOM würde auf dem Live-Host „headers already sent" auslösen.

Das Ausgabeverzeichnis `release/` ist in `.gitignore` ausgenommen. Das resultierende ZIP wird via **Plugins → Installieren → Plugin hochladen** eingespielt.

## Zusammenspiel mit dem Update-Pfad

```
git tag vX.Y.Z + push
   └─ GitHub Action: minify → $loadMinified=true → ZIP → Release-Asset
          └─ Plugin-Update-Checker (im installierten Plugin)
                 └─ erkennt neuen Release → bietet Update im WP-Dashboard
```

Damit ein Update beim Nutzer ankommt, muss der Tag-Name zur Versionsangabe passen, die der Update-Checker vergleicht (Plugin-Header `Version:` / `WP_SDTRK_VERSION`). Details zur Konsum-Seite: [06 › Plugin-Update-Checker](../06-integrations.md#plugin-update-checker).
