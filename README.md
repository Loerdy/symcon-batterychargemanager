# Battery Charge Manager für IP-Symcon

[Deutsch](#battery-charge-manager-für-ip-symcon) | [English](#battery-charge-manager-for-ip-symcon)

Diese IP-Symcon-Library stellt eine Grundlage zur automatischen Steuerung eines Ladegeräts anhand des Batteriestands eines Tablets, Smartphones oder eines ähnlichen Akkugeräts bereit.

> **Aktueller Stand:** Das Repository enthält zunächst nur die Modulgrundstruktur und die Konfiguration. Die eigentliche Ladesteuerung ist noch nicht implementiert.

## Voraussetzungen

- IP-Symcon ab Version 7.0
- eine in IP-Symcon vorhandene Variable für den Batteriestand
- eine in IP-Symcon schaltbare Variable für den Ladeaktor

## Enthaltenes Modul

### Akku-Ladesteuerung

Eine Instanz verwaltet genau ein Akkugerät. Vorgesehen sind folgende Einstellungen:

- Batterievariable
- schaltbare Variable des Ladeaktors
- Einschaltschwelle, standardmäßig 20 %
- Ausschaltschwelle, standardmäßig 80 %

Die spätere Steuerung soll die Batterievariable ereignisbasiert überwachen. Bei einem Batteriestand kleiner oder gleich der Einschaltschwelle soll der Ladeaktor eingeschaltet werden. Bei einem Batteriestand größer oder gleich der Ausschaltschwelle soll er ausgeschaltet werden. Zwischen den Schwellen bleibt sein Zustand unverändert. Die Einschaltschwelle muss kleiner als die Ausschaltschwelle sein.

## Installation

Das Modul kann über die IP-Symcon-Modulverwaltung aus diesem GitHub-Repository installiert werden:

```text
https://github.com/Loerdy/symcon-batterychargemanager.git
```

1. In IP-Symcon unter **Kern Instanzen → Modules** das Repository hinzufügen.
2. Den gewünschten Branch auswählen.
3. Die Module aktualisieren beziehungsweise installieren.

---

# Battery Charge Manager for IP-Symcon

[Deutsch](#battery-charge-manager-für-ip-symcon) | [English](#battery-charge-manager-for-ip-symcon)

This IP-Symcon library provides the foundation for automatically controlling a charger based on the battery level of a tablet, smartphone, or similar battery-powered device.

> **Current status:** The repository currently contains only the basic module structure and configuration. The actual charge-control logic has not been implemented yet.

## Requirements

- IP-Symcon version 7.0 or later
- an IP-Symcon variable containing the battery level
- a switchable IP-Symcon variable controlling the charger

## Included Module

### Battery Charge Controller

Each instance manages exactly one battery-powered device. The following settings are planned:

- battery-level variable
- switchable charger variable
- switch-on threshold, 20% by default
- switch-off threshold, 80% by default

The future controller will monitor the battery variable through events. At or below the switch-on threshold, it will switch the charger on. At or above the switch-off threshold, it will switch the charger off. Between the thresholds, the current state remains unchanged. The switch-on threshold must be lower than the switch-off threshold.

## Installation

Install the module from this GitHub repository using IP-Symcon's module management:

```text
https://github.com/Loerdy/symcon-batterychargemanager.git
```

1. In IP-Symcon, open **Core Instances → Modules** and add the repository.
2. Select the desired branch.
3. Update or install the modules.
