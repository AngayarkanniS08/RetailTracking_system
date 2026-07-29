# Customer Module Schema & Migration Ownership

## Module Responsibility
The **Customer** module owns customer profiles, credit ledger balances (Kadan tracking), and customer payment records.

## Owned Database Objects
- Tables:
  - `customer_credits`
  - `customer_payments`

## Architectural Rules
1. Financial balances and credit tracking must maintain strict auditability.
2. Ledger tables require double-entry or append-only transaction logs.
3. Direct modification of customer credit records by other modules is forbidden.
