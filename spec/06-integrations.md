# 06 — Integrationen & externe Abhängigkeiten

## Digistore24-Decryption {#digistore24-decryption}

- **Klasse:** `Wp_Sdtrk_Decrypter_ds24`
- **Datei:** `public/class-wp-sdtrk-decryptor-ds24.php`
- **Zweck:** Digistore24 kann Thank-You-Page-Parameter (Käuferdaten, Bestellinfos) **verschlüsselt** übergeben. Das Plugin entschlüsselt sie serverseitig, bevor das Event gebildet wird — so bleibt der Schlüssel im Backend.

### Aktivierung

| Option | Bedeutung |
|--------|-----------|
| `ds24_encrypt_data` | Entschlüsselung aktivieren |
| `ds24_encrypt_data_key` | geheimer Thank-You-Key |

Ist beides gesetzt, fügt `Wp_Sdtrk_Public` den Service `ds24` der Decrypter-Service-Liste hinzu (→ `wp_sdtrk_decrypter.services`). Der Browser-Decrypter schickt die verschlüsselten Parameter per AJAX zurück, der Server entschlüsselt.

### Verfahren

- **Algorithmus:** AES-256-CBC.
- **Schlüssel:** `SHA256(secret_key)`.
- **IV:** 16 zufällige Bytes, als Hex dem Ciphertext vorangestellt.
- **Format:** `ds24{hexIV}-{base64}` (Base64 mit `_e`→`=`, `_p`→`+`).
- **Ablauf:** Prefix `ds24` prüfen → Validierungs-Zeichen → IV extrahieren → Base64 dekodieren → AES-256-CBC entschlüsseln → Klartext-Prefix gegen die ersten Zeichen des Secrets validieren → Parameter zurückgeben.
- **Optional (auskommentiert):** SHA512-`SHASIGN`-Validierung über sortierte Parameter.

### Generisches Decrypter-Muster

Der Entschlüsselungspfad in `Wp_Sdtrk_Public::decryptData()` ist serviceunabhängig aufgebaut:

```php
$className = 'Wp_Sdtrk_Decrypter_' . $service;            // z. B. Wp_Sdtrk_Decrypter_ds24
$key       = WP_SDTRK_Helper_Options::get_string_option($service . "_encrypt_data_key");
if (class_exists($className) && $key) {
    $decrypter = new $className($key, $encryptedData);
    $decryptedData = $decrypter->getDecryptedData();
}
```

→ Weitere Zahlungsanbieter ließen sich durch eine `Wp_Sdtrk_Decrypter_{service}`-Klasse + `{service}_encrypt_data_key`-Option ergänzen. (README nennt historisch auch CopeCart-Support.)

---

## Plugin-Update-Checker

- **Paket:** `yahnis-elsts/plugin-update-checker` (v5), `vendor/yahnis-elsts`.
- **Quelle:** GitHub `Ranktotop/wp-sdtrk` mit aktivierten Release-Assets (`enableReleaseAssets()`).
- **Wirkung:** Updates erscheinen im WP-Dashboard wie bei wp.org-Plugins, Download aus GitHub-Releases.

---

## Redux Framework

- **Paket:** `wpackagist-plugin/redux-framework`, `vendor/redux`.
- **Rolle:** komplettes Options-/Metabox-UI ([04](04-admin-and-options/README.md)).
- Geladen in `load_dependencies()` über `vendor/redux/redux-core/framework.php`.

---

## Composer-Abhängigkeiten (`composer.json`)

| Paket | Version | Zweck |
|-------|---------|-------|
| `wpackagist-plugin/redux-framework` | `*` | Admin-Options-Framework |
| `yahnis-elsts/plugin-update-checker` | `^5.5` | GitHub-Updates |
| `composer/installers` | `^2.3` | Installer-Pfade |

PSR-4: `Rankt\WpSmartServerSideTracking\` → `src/` (deklariert; `src/` existiert im Repo nicht — [99 Befunde](99-findings.md)).

---

## Cookie-Consent: Borlabs

Browser-seitige Integration für Borlabs Cookie v2 **und** v3. Details: [03 › Consent-Management](03-browser-tracking/consent-management.md).
