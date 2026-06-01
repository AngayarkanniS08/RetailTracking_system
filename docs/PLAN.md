# PLAN: Decoupled Multi-Container PHP Architecture Refactoring

This plan details the steps to split the Retail Tracking System into Frontend (PHP/Apache) and Backend (PHP/Apache API) containers, keep the standard `.php` view files, delete duplicate `.html` files, and restructure the folders directly at the root.

---

## 🧐 Resolve Duplication: Keep PHP Views, Delete HTML Modules
We will keep only the standard `.php` views and remove the redundant `.html` files.

### The Resolution Strategy:
1. **Frontend Container runs PHP/Apache:** Since our views (like `login.php`, `register.php`, and `layouts/header.php`) use the `.php` extension and are server-side required by `index.php`, the Frontend container will run **PHP/Apache** (on port `8080`) to render the pages.
2. **Delete `.html` duplicates:** We will completely delete the static `.html` files under `Frontend/Modules/` (like `login.html`, `dashboard.html`, `product.html`, etc.).
3. **Restructure directories directly at the root:** We will remove the `Frontend/` wrapper folder and place the `public/` and `views/` directories directly in the root directory.

---

## 🏗️ Architectural Overview & Root Directory Structure
We will organize the root directory to separate backend API code from frontend presentation:

```plaintext
RetailTracking_system/
├── Database/                   # Backend: Migrations and Seeds (PostgreSQL scripts)
├── src/                        # Backend: PHP core & API endpoints
│   ├── Core/
│   ├── Modules/
│   ├── config/
│   ├── vendor/
│   ├── Routes.php
│   └── index.php               # Backend entrypoint: Handles stateless API routes only
├── public/                     # Frontend: Static assets (Exposed to browser)
│   └── assets/
│       ├── css/                # style.css, auth.css, theme.css, dashboard.css
│       ├── js/                 # utils.js, login.js, register.js, ProductMaster.js, Sidebar.js, forgot-password.js, reset-password.js
│       └── images/             # logo.png
├── views/                      # Frontend: PHP views and layouts
│   ├── auth/                   # login.php, register.php, ForgotPassword.php, ResetPassword.php
│   ├── billing/                # index.php, receipt.php
│   ├── customer/               # index.php
│   ├── inventory/              # index.php
│   ├── layouts/                # header.php, footer.php, topbar.php, sidebar.php, modals.php
│   ├── product/                # index.php
│   ├── reports/                # dashboard.php, daily_sales.php, stockintel.php
│   └── vendor/                 # index.php, history.php
├── index.php                   # Frontend entrypoint: Bootstraps session/token and loads views
├── Dockerfile                  # Base Dockerfile for PHP/Apache (Used by both services)
├── docker-compose.yml          # Container orchestration (Exposes frontend port 8080)
└── Makefile                    # Make commands
```

---

## 🛠️ Detailed Implementation Steps

### Phase 1: Docker & Nginx/Apache Setup
- **Docker Compose Update:** Rewrite `docker-compose.yml` to define:
  - `db` (PostgreSQL)
  - `api` (Backend API container, runs Apache/PHP on internal port 80, builds from root `Dockerfile`, mounts `src/` and `Database/`)
  - `frontend` (Frontend Web container, runs Apache/PHP on external port `8080:80`, builds from root `Dockerfile`, mounts `views/`, `public/`, and root `index.php`)
- **Backend Rewrite:** Update `.htaccess` in the root of the backend to route `/api/*` requests to `/src/index.php`.

### Phase 2: Restructure Directories & Resolve Duplication
- Create root `public/assets/` and `views/` folders in the workspace.
- Move all `.php` files from `Frontend/views/` to the root `views/`.
- Move CSS, JS, and image assets from `Frontend/assets/` to `public/assets/...`.
- Delete the old `Frontend/` and `JsBackup/` folders (completely removing the `.html` duplicates).

### Phase 3: Path and Link Updates
- **Update asset links in PHP views:** Edit stylesheet links, script sources, and images in all views (like `header.php`, `login.php`, `ForgotPassword.php`) to point to `/public/assets/...`.
- **Update script redirects:** Change redirects inside JS scripts to use `/index.php` or `index.php?action=...` clean routes.
- **Set include_path in Frontend:** In the root `index.php` (frontend entrypoint), set the include path so that requires like `require_once 'views/layouts/header.php'` resolve out-of-the-box.
- **Simplify `src/index.php`:** Strip out all session-based web routing and page loading. Only keep stateless API route dispatching.

---

## 👁️ Preview & DevTools Verification Plan

### Previewing with `/preview`
- We will start the Docker services using `docker compose up -d --build`.
- Accessing `http://localhost:8080/index.php?action=login` will load the login page.

### Chrome DevTools MCP Audit
1. **Navigate to Login:** Open `http://localhost:8080/index.php?action=login` in Chrome DevTools MCP.
2. **Console Audit:** Run `list_console_messages` to ensure all JS scripts and styles load without any errors.
3. **Network Audit:** Run `list_network_requests` to verify `/public/assets/...` and logo asset load successfully with `200 OK`.
4. **Interactive Verification:** Test user flow (login, Category creation, Product Master navigation) by filling/clicking fields and checking screens via `take_screenshot`.
