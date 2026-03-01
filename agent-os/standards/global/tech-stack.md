## Tech stack

This document defines the technical stack for the RackAudit project.

### Framework & Runtime
- **Application Framework:** Laravel 12.44.0
- **Language/Runtime:** PHP 8.4.16
- **Package Manager:** Composer (PHP), npm (JavaScript)

### Frontend
- **JavaScript Framework:** Vue 3.5.25 with Inertia.js 2.2.19
- **CSS Framework:** Tailwind CSS 4.1.17
- **Build Tool:** Vite with @laravel/vite-plugin-wayfinder 0.1.7

### Backend
- **Authentication:** Laravel Fortify 1.33.0
- **Route Generation:** Laravel Wayfinder 0.1.12
- **CLI Prompts:** Laravel Prompts 0.3.8
- **MCP Server:** Laravel MCP 0.5.1

### Database & Storage
- **Database:** MySQL
- **ORM/Query Builder:** Eloquent ORM

### Testing & Quality
- **Test Framework:** Pest 4.2.0 (with PHPUnit 12.5.3)
- **PHP Linting/Formatting:** Laravel Pint 1.26.0
- **JS Linting:** ESLint 9.39.1
- **JS Formatting:** Prettier 3.7.4

### Development Environment
- **Local Development:** Laravel Sail 1.51.0, Laravel Herd
