# AGENTS.md

## Projektziel

Dieses Repository enthält die IP-Symcon-Library `BatteryChargeManager` mit dem Modul `BatteryChargeController`. Eine Modulinstanz verwaltet genau ein Akkugerät und soll dessen Ladezustand ereignisbasiert auswerten sowie einen vorhandenen Ladeaktor zwischen konfigurierbaren Schwellwerten schalten.

## Maßgebliche Dokumentation

Für alle Fragen zur IP-Symcon-API und zur Entwicklung von IP-Symcon-Modulen ist die aktuelle offizielle Symcon-Dokumentation maßgeblich.

- Einstiegspunkt für KI-Agenten: <https://www.symcon.de/de/llms.txt>
- Funktionsindex: <https://www.symcon.de/de/llms/function-index.md>

Bei Bedarf den dort enthaltenen Links zur Detaildokumentation folgen. Das gilt insbesondere für:

- das SDK für PHP-Module
- Module und deren Lifecycle
- Konfigurationsformulare
- PHP-Funktionen
- Variablen und Variablenprofile
- Instanzen
- Timer
- Nachrichten
- Debug-Ausgaben

Projektcode und vorhandene, nachweislich funktionierende Schnittstellen können ergänzend als Referenz dienen. Bei Widersprüchen oder Unsicherheit hat die aktuelle offizielle Dokumentation Vorrang.

## Regeln für IP-Symcon-Code

- Keine IP-Symcon-Funktionen, APIs, Konstanten, Nachrichten oder Rückgabewerte erfinden.
- Vor der Verwendung einer noch nicht im Projekt nachgewiesenen IP-Symcon-API deren aktuellen Funktionsnamen, Parameter, Rückgabewerte und Verfügbarkeit in der unterstützten IP-Symcon-Version anhand der offiziellen Dokumentation prüfen.
- Dies gilt insbesondere für Funktionen und Methoden aus den Bereichen `IPS_*`, `SetValue*`, `GetValue*`, `RegisterVariable*`, `RegisterProperty*`, `RegisterTimer`, `SetTimerInterval` und `SendDebug`.
- Bei Unsicherheit zuerst die offizielle Dokumentation konsultieren und nicht aufgrund von Vermutungen implementieren.
- Bestehende öffentliche Schnittstellen, Idents, Property-Namen, Präfixe und gespeicherte Konfigurationen vor Änderungen auf Kompatibilität prüfen.
- Projektweite öffentliche Funktionspräfixe müssen mit dem in `module.json` festgelegten Präfix übereinstimmen.

## Modulstruktur

- `library.json` enthält die Metadaten und Kompatibilitätsangaben der Library.
- Jedes Modul liegt in einem eigenen Verzeichnis.
- `module.json` enthält die Modulmetadaten, den Modultyp, Anforderungen, implementierte Schnittstellen und das Funktionspräfix.
- `module.php` enthält die Modulklasse und deren Verhalten.
- `form.json` enthält das statische Konfigurationsformular, sofern das Formular nicht begründet dynamisch erzeugt wird.
- `locale.json` enthält gegebenenfalls Übersetzungen und muss mit den verwendeten Texten und Sprachen konsistent gehalten werden.
- Metadaten, Namen, GUIDs, Präfixe und Kompatibilitätsangaben über alle Dateien hinweg konsistent halten.
- JSON-Dateien müssen syntaktisch gültig sein und der von IP-Symcon dokumentierten Struktur entsprechen.

## Modul-Lifecycle

- Vor Änderungen an `Create()`, `ApplyChanges()` oder `Destroy()` die aktuelle Symcon-Dokumentation zum Modul-Lifecycle prüfen.
- Properties, Variablen, Nachrichten und gegebenenfalls Timer an den von IP-Symcon vorgesehenen Stellen registrieren beziehungsweise verwalten.
- `parent::Create()`, `parent::ApplyChanges()` und gegebenenfalls `parent::Destroy()` entsprechend der dokumentierten Lifecycle-Anforderungen aufrufen.
- `Create()` nur für die dafür vorgesehene initiale Registrierung und Einrichtung verwenden.
- Konfigurationsabhängige Einrichtung und Aktualisierung in `ApplyChanges()` durchführen.
- Aufräumarbeiten in `Destroy()` nur implementieren, wenn sie erforderlich und durch den Lifecycle eindeutig gedeckt sind.
- Wiederholte Aufrufe von `ApplyChanges()` berücksichtigen; Initialisierung und Registrierung müssen den dokumentierten Erwartungen von IP-Symcon entsprechen.
- Timer nur einführen, wenn sie fachlich erforderlich sind. Timerintervalle bei deaktivierter oder unvollständiger Konfiguration entsprechend abschalten.

## Projektspezifische Regeln

- Eine Instanz von `BatteryChargeController` repräsentiert genau ein Akkugerät.
- Zustandsänderungen sollen ereignisbasiert verarbeitet werden; kein unnötiges zyklisches Polling einführen.
- Die Einschaltschwelle muss kleiner als die Ausschaltschwelle sein.
- Bei einem Batteriestand kleiner oder gleich der Einschaltschwelle soll der Ladeaktor eingeschaltet werden.
- Bei einem Batteriestand größer oder gleich der Ausschaltschwelle soll der Ladeaktor ausgeschaltet werden.
- Zwischen beiden Schwellwerten bleibt der Zustand des Ladeaktors unverändert.
- Vor der Ansteuerung des Ladeaktors die ausgewählte Variable und deren dokumentierte Schaltmöglichkeit prüfen.

## GUIDs

- Niemals GUIDs erfinden oder eigenständig erzeugen.
- Ausschließlich vom Benutzer bereitgestellte oder im Repository bereits nachweisbare GUIDs verwenden.
- Vor jeder zusätzlich benötigten GUID beim Benutzer nachfragen.

## PHP und Änderungen

- PHP-Code muss mit der von der Library unterstützten IP-Symcon-Version und deren PHP-Laufzeit kompatibel sein.
- Den bestehenden Coding-Stil beibehalten.
- PHP mit `declare(strict_types=1);` schreiben.
- Öffentliche Modulmethoden und IP-Symcon-Lifecycle-Methoden mit passenden Typdeklarationen versehen, soweit die dokumentierte IP-Symcon-Signatur dies zulässt.
- Änderungen klein, zielgerichtet und nachvollziehbar halten.
- Keine unnötigen Refactorings, Abhängigkeiten, Hilfsbibliotheken oder Abstraktionen einführen.
- Bestehende Schnittstellen und deren Aufrufer vor einer Änderung ermitteln und berücksichtigen.
- Kommentare nur verwenden, wenn sie den Zweck oder nicht offensichtliche Entscheidungen erklären.
- Dokumentation bei sichtbaren Verhaltens- oder Konfigurationsänderungen aktualisieren.
- Änderungen mit Nutzerrelevanz unter `[Unreleased]` in `CHANGELOG.md` dokumentieren.
- Bei Unklarheiten keine weitreichenden Annahmen treffen. Zuerst Dokumentation und Projektcode prüfen; falls die Entscheidung weiterhin offen oder eine zusätzliche GUID erforderlich ist, beim Benutzer nachfragen.

## Versionierung

- Git-Releases verwenden Semantic Versioning.
- In `library.json` wird `Major.Minor` im Feld `version` und `Patch` im Feld `build` abgebildet.
- Beispiel: Git-Release `v2.0.1` entspricht `"version": "2.0"` und `"build": 1`.
- Bei zukünftigen Releases beide Felder gemeinsam prüfen und passend zur vorgesehenen Release-Version anpassen.

## Git-Workflow

- Vor Änderungen immer `git status --short --branch` prüfen und bestehende Änderungen des Benutzers respektieren.
- Keine Commits ohne ausdrückliche Aufforderung ausführen.
- Keinen Push ohne ausdrückliche Aufforderung ausführen.
- Für Tests in IP-Symcon gilt zwingend die Reihenfolge: Commit, Push, Test. Änderungen können erst nach dem Push in IP-Symcon getestet werden.

## Prüfungen nach Änderungen

Die Prüfungen an Art und Umfang der Änderung anpassen. Vor Abschluss mindestens:

1. Alle geänderten JSON-Dateien auf gültige Syntax und passende Grundstruktur prüfen.
2. Alle geänderten PHP-Dateien mit `php -l` prüfen, sofern PHP verfügbar ist.
3. Relevante projektspezifische Tests oder statische Prüfungen ausführen, sofern vorhanden und lokal möglich.
4. `git diff --check` ausführen.
5. Den vollständigen Diff auf unbeabsichtigte Änderungen, erfundene APIs, Debug-Reste und sensible Daten prüfen.
6. `git status --short` prüfen und sicherstellen, dass nur beabsichtigte Dateien geändert wurden.

Wenn eine Prüfung nicht ausgeführt werden kann, dies im Abschluss ausdrücklich nennen.
