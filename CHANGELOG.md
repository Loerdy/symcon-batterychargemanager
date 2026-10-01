# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project uses semantic versioning.

## [Unreleased]

### Added

- Add controllable instance variables for the switch-on and switch-off thresholds and persist changes in the module configuration.
- Add optional output-only status variables for the battery level and the actual charger actuator state.
- Add configuration options that create or remove each optional status variable independently.
- Monitor charger actuator updates when its optional status variable is enabled.
- Add a public `ResetThresholds()` method and a configuration button that restore both thresholds to their defaults of 20% and 80% and apply them together.
- Document how to create a user-managed script for resetting the thresholds from the WebFront or visualization.

### Changed

- Validate that both thresholds are within the range from 0% to 100% when applying the configuration or handling an action.
- Display the optional battery status as an Integer using the `~Battery.100` profile and round Float source values to whole percentages without reducing the precision used by the charge control logic.
- Reject invalid threshold changes with debug and log messages instead of exposing uncaught exceptions for normal input errors.
- Return understandable validation messages to the visualization when threshold actions are rejected.
- Display specific instance status messages for the different configuration errors.
- Use the `~Switch` system profile for the optional charger actuator status.
- Reuse one public reset method for the configuration button and a user-created visualization script.

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
