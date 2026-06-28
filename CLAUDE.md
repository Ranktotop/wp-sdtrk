# CLAUDE.md — Projektregeln für `wp-sdtrk`

## Die Spec ist die Source of Truth

Die Spezifikation unter [`spec/`](spec/README.md) (Einstieg: [`SPEC.md`](SPEC.md)) ist die **maßgebliche, aktuelle** Beschreibung des Plugins. Sie hat Vorrang: Bei Widersprüchen zwischen Spec und Code ist der Widerspruch zu klären und anschließend **beide** in Einklang zu bringen — nicht stillschweigend übergehen.

### Pflicht-Workflow bei jeder Feature-Änderung

Wenn ein Feature **hinzugefügt, entfernt oder bearbeitet** wird:

1. **Zuerst die Spec prüfen.** Vor der Code-Änderung die betroffenen Spec-Dateien lesen, um den dokumentierten Ist-Zustand zu verstehen.
2. **Code ändern.**
3. **Spec aktualisieren**, sodass sie den neuen Ist-Zustand exakt widerspiegelt. Eine Änderung gilt erst als fertig, wenn die Spec nachgeführt ist.
4. Den passenden Index/`README.md` der betroffenen Sektion sowie Querverweise auf Konsistenz prüfen.

> Eine Code-Änderung ohne entsprechende Spec-Aktualisierung ist unvollständig.

### Die Spec ist KEIN Changelog

Die Spec dokumentiert **ausschließlich den aktuellen Zustand** des Codes — wie er **jetzt** ist.

- **Keine** Historie, keine „vorher/nachher", kein „früher war X, jetzt ist Y".
- **Keine** Versions- oder Datumsvermerke zu einzelnen Änderungen.
- Keine Aussagen darüber, wie der Code gestern war oder morgen sein könnte.
- Beim Bearbeiten: veraltete Beschreibungen **ersetzen**, nicht ergänzen oder durchstreichen.

Historie gehört in Git/Commits, nicht in die Spec.

## Aufbau der Spec

Modular nach Themen, jede Ebene hat eine `README.md` als Index. Beim Anlegen neuer Inhalte das bestehende Schema beibehalten (nummerierte Sektionsordner, Index-Datei pro Ordner, relative Markdown-Links zwischen den Dateien).

| Sektion | Thema |
|---------|-------|
| [00](spec/00-overview.md) | Überblick, Feature-Matrix, Glossar |
| [01](spec/01-architecture/README.md) | Architektur (Bootstrap, Loader, Konventionen, Lebenszyklus) |
| [02](spec/02-server-tracking/README.md) | Server-Tracking (Conversion API / S2S) |
| [03](spec/03-browser-tracking/README.md) | Browser-Tracking (JavaScript) |
| [04](spec/04-admin-and-options/README.md) | Admin & Optionen |
| [05](spec/05-data-model/README.md) | Datenmodell |
| [06](spec/06-integrations.md) | Integrationen & Abhängigkeiten |
| [99](spec/99-findings.md) | Befunde & offene Punkte |

> Die Sektion [99 Befunde](spec/99-findings.md) ist die einzige Stelle für bekannte Bugs/offene Punkte. Wird ein dort gelisteter Punkt behoben, ist er aus den Befunden zu entfernen und der betroffene Bereich der Spec auf den neuen Ist-Zustand zu bringen.
