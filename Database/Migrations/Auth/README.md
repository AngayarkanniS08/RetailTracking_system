# Auth Module Schema & Migration Ownership

## Module Responsibility
The **Auth** module owns all identity, authentication, session, credential, and permission tables.

## Owned Database Objects
- Tables:
  - `users`
  - `password_resets`
- Indexes & Constraints:
  - `users_pkey`, `users_username_key`, `users_email_key`
  - `password_resets_pkey`

## Architectural Rules
1. No other module may directly alter or join across tables owned by the `Auth` module via direct DDL.
2. Authentication tokens, user IDs, and password hashes are strictly managed by `src/Modules/Auth`.
3. Cross-module queries requiring user metadata must resolve via application services or shared contracts.
