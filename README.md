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

Die beiden Schwellwerte werden zusätzlich als bedienbare Prozentvariablen unter der Instanz bereitgestellt. Änderungen über diese Variablen werden unmittelbar in die persistente Modulkonfiguration übernommen und für die Ladesteuerung verwendet.

Über die Instanzkonfiguration können beide Schwellwerte gemeinsam auf die Standardwerte 20 % und 80 % zurückgesetzt werden. Die Werte werden anschließend einmal übernommen und der aktuelle Batteriestand wird unmittelbar neu ausgewertet.

Optional können ein nicht bedienbarer Batteriestatus und ein nicht bedienbarer Ladeaktorstatus unter der Instanz angezeigt werden. Der Batteriestatus ist eine Integer-Variable mit dem IP-Symcon-Standardprofil `~Battery.100`. Float-Werte der konfigurierten Batterievariable werden für diese Anzeige mathematisch auf volle Prozent gerundet; die Ladesteuerung wertet weiterhin den ursprünglichen präzisen Wert aus. Der Ladeaktorstatus verwendet das IP-Symcon-Systemprofil `~Switch`, folgt dem tatsächlichen Zustand der externen Boolean-Variable und berücksichtigt auch externe Zustandsänderungen.

Deaktivierte optionale Statusvariablen werden vollständig entfernt und nicht nur ausgeblendet. Beim erneuten Aktivieren kann deshalb eine neue Objekt-ID entstehen; vorhandene Links und Archivzuordnungen können dadurch betroffen sein.

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

Both thresholds are also provided as controllable percentage variables below the instance. Changes made through these variables are immediately stored in the persistent module configuration and used by the charge controller.

Both thresholds can be reset together to their defaults of 20% and 80% through the instance configuration. The values are then applied once and the current battery level is evaluated immediately.

An output-only battery status and an output-only charger actuator status can be displayed below the instance independently. The battery status is an Integer variable using the IP-Symcon standard profile `~Battery.100`. Float values from the configured battery variable are mathematically rounded to whole percentages for this display; the charge controller continues to evaluate the original precise value. The charger actuator status uses the IP-Symcon system profile `~Switch`, follows the actual state of the external Boolean variable, and also reflects external state changes.

Disabled optional status variables are removed completely instead of merely being hidden. Enabling them again may therefore create a new object ID; existing links and archive assignments may be affected.

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
