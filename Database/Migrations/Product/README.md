# Product Module Schema & Migration Ownership

## Module Responsibility
The **Product** module owns catalog domain data including categories, subcategories, product master records, daily sales aggregations, and stock alert configurations.

## Owned Database Objects
- Tables:
  - `categories`
  - `subcategories`
  - `products`
- Foreign Keys:
  - `fk_subcategories_category_id` -> `categories(id)`
  - `fk_products_category_id` -> `categories(id)`
  - `fk_products_subcategory_id` -> `subcategories(id)`

## Architectural Rules
1. Schema changes to product catalog or inventory levels belong exclusively inside `Database/Migrations/Product/`.
2. FKs pointing to external modules (e.g. `user_id`) must remain non-cascading or handled explicitly via domain events.
