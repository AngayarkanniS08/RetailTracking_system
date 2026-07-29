# Dashboard Module Schema & Migration Ownership

## Module Responsibility
The **Dashboard** module owns reporting views, analytical aggregates, and executive dashboard widget preferences.

## Architectural Rules
1. Analytical views and materialized tables must not introduce locks on transactional OLTP tables.
