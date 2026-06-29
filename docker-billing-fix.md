# Docker and Billing Layout Fixes

## Goal
Resolve the Docker UI container creation failure by removing the unnecessary `Billing/Controller/Api` shadow mount, and fix the billing product visibility/availability bug by aligning `Billing.js` references to the correct database column alias `quantity`.

## Tasks
- [ ] Task 1: Modify `docker-compose.yml` to remove the shadow mount `- /var/www/html/src/Modules/Billing/Controller/Api` under the `app-ui` service.
- [ ] Task 2: Modify `public/assets/js/Billing.js` to replace all references to `remaining_qty` with `quantity` (with defensive `|| 0` fallbacks).
- [ ] Task 3: Verify the changes using Docker commands and run standard checklist.

## Done When
- The `retail_pos_ui` service container builds and runs without "read-only filesystem" volume initialization errors.
- Products in the POS/Billing grid render with correct stock values instead of `undefined`.
