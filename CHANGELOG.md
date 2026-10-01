# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project uses semantic versioning.

## [Unreleased]

## [0.1.0] - 2026-10-01

### Added

- Add the initial `BatteryChargeManager` IP-Symcon library.
- Add the `BatteryChargeController` module with the German alias `Akku-Ladesteuerung`.
- Add configuration of an Integer or Float battery variable and a Boolean charger actuator with an action.
- Add configurable switch-on and switch-off thresholds with defaults of 20% and 80%.
- Add event-based battery monitoring using IP-Symcon variable messages.
- Add hysteresis control and charger switching through the configured variable action.
- Avoid repeated switching commands when the charger actuator already has the desired state.
- Validate configured variables, variable types, actuator action, thresholds, and battery values.
- Evaluate the current battery level after applying the configuration or restarting IP-Symcon.
- Detect deletion of the configured battery variable or charger actuator and set an error state.

### Changed

- Use `BCMC` as the public module prefix.
