# Changelog

All notable changes to this project will be documented in this file.

The format is based on Keep a Changelog and this project uses semantic versioning.

## [Unreleased]

### Added

- Add the initial IP-Symcon library and module structure.
- Add configuration fields for the battery variable, charger actuator, and charge thresholds.
- Add event-based battery monitoring and hysteresis-controlled charger switching.
- Validate configured variables, actions, thresholds, and battery values.
- Evaluate the current battery level after applying the configuration.
- Detect deletion of the configured battery variable or charger actuator.

### Changed

- Change the public module prefix from `BCM` to `BCMC`.
