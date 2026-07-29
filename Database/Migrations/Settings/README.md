# Settings Module Schema & Migration Ownership

## Module Responsibility
The **Settings** module owns system configuration, store profile, tax rules, invoice sequences, and feature flags.

## Architectural Rules
1. Global configuration settings must be key-value or structured JSON with strict schema validation.
