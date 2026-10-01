# Battery Charge Manager für IP-Symcon

[Deutsch](#battery-charge-manager-für-ip-symcon) | [English](#battery-charge-manager-for-ip-symcon)

Diese IP-Symcon-Library steuert ein Ladegerät automatisch anhand des Batteriestands eines Tablets, Smartphones oder eines ähnlichen Akkugeräts.

## Voraussetzungen

- IP-Symcon ab Version 7.0
- eine in IP-Symcon vorhandene Integer- oder Float-Variable für den Batteriestand
- eine in IP-Symcon vorhandene Boolean-Variable mit Action für den Ladeaktor

## Enthaltenes Modul

### Akku-Ladesteuerung

Eine Instanz verwaltet genau ein Akkugerät. Konfiguriert werden:

- Batterievariable vom Typ Integer oder Float
- Ladeaktor als Boolean-Variable mit Action
- Einschaltschwelle, standardmäßig 20 %
- Ausschaltschwelle, standardmäßig 80 %

Die Batterievariable wird ereignisbasiert über die dokumentierte IP-Symcon-Nachricht `VM_UPDATE` überwacht. Ein zyklisches Polling findet nicht statt. Bei einem Batteriestand kleiner oder gleich der Einschaltschwelle wird der Ladeaktor eingeschaltet. Bei einem Batteriestand größer oder gleich der Ausschaltschwelle wird er ausgeschaltet. Zwischen den Schwellen bleibt sein Zustand unverändert. Diese Hysterese verhindert unnötiges Hin- und Herschalten; die Einschaltschwelle muss deshalb kleiner als die Ausschaltschwelle sein.

Nach dem Übernehmen der Konfiguration sowie nach einem Neustart wertet das Modul den aktuellen Batteriestand einmal aus. Ein Schaltbefehl wird nur gesendet, wenn der Ladeaktor noch nicht den gewünschten Zustand hat.

Wird die konfigurierte Batterievariable oder der Ladeaktor während des Betriebs gelöscht, wechselt die Modulinstanz in einen Fehlerzustand und führt keine weitere Schaltung aus.

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

This IP-Symcon library automatically controls a charger based on the battery level of a tablet, smartphone, or similar battery-powered device.

## Requirements

- IP-Symcon version 7.0 or later
- an IP-Symcon Integer or Float variable containing the battery level
- an IP-Symcon Boolean variable with an action controlling the charger

## Included Module

### Battery Charge Controller

Each instance manages exactly one battery-powered device. The following settings are configured:

- Integer or Float battery-level variable
- Boolean charger variable with an action
- switch-on threshold, 20% by default
- switch-off threshold, 80% by default

The controller monitors the battery variable through the documented IP-Symcon `VM_UPDATE` message. It does not use periodic polling. At or below the switch-on threshold, it switches the charger on. At or above the switch-off threshold, it switches the charger off. Between the thresholds, the current state remains unchanged. This hysteresis prevents unnecessary switching; the switch-on threshold must therefore be lower than the switch-off threshold.

After applying the configuration and after a restart, the module evaluates the current battery level once. A switching command is sent only when the charger actuator does not already have the desired state.

If the configured battery variable or charger actuator is deleted during operation, the module instance enters an error state and performs no further switching.

## Installation

Install the module from this GitHub repository using IP-Symcon's module management:

```text
https://github.com/Loerdy/symcon-batterychargemanager.git
```

1. In IP-Symcon, open **Core Instances → Modules** and add the repository.
2. Select the desired branch.
3. Update or install the modules.
