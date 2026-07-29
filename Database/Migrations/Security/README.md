# Security Module Schema & Migration Ownership

## Module Responsibility
The **Security** module owns security audit logs, rate limiters, token blocklists, and IP access policies.

## Architectural Rules
1. Audit tables must be append-only and protected against unauthorized mutation or truncation.
