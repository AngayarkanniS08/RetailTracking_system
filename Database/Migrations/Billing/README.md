# Billing Module Schema & Migration Ownership

## Module Responsibility
The **Billing** module owns sales invoices, bill line items, tax computations (GST/HSN), and invoice payment transactions.

## Owned Database Objects
- Tables:
  - `bills`
  - `bill_items`
  - `payments`

## Architectural Rules
1. Invoices and line items are immutable once finalized and paid.
2. Price calculations and GST rounding follow strict domain service rules.
