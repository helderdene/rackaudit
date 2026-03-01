# Tech Stack

## Backend

| Technology | Version | Purpose |
|------------|---------|---------|
| PHP | 8.4.x | Server-side programming language |
| Laravel | 12.x | PHP web application framework |
| MySQL | 8.x | Relational database |
| Laravel Sanctum | Latest | API token authentication |
| Laravel Fortify | 1.x | Headless authentication backend |
| Spatie Laravel-Permission | Latest | Role-based access control (RBAC) |
| Laravel Excel | Latest | Excel/CSV import and export |
| Laravel DomPDF | Latest | PDF report generation |
| Laravel Echo | Latest | Real-time event broadcasting |
| Pusher/Soketi | Latest | WebSocket server for real-time updates |
| Laravel Storage | Built-in | File storage abstraction (S3 compatible) |

## Frontend

| Technology | Version | Purpose |
|------------|---------|---------|
| Vue.js | 3.x | Frontend JavaScript framework |
| Inertia.js | 2.x | Server-side rendering adapter |
| Tailwind CSS | 4.x | Utility-first CSS framework |
| Pinia | Latest | Vue state management |
| D3.js or Vue Flow | Latest | Connection diagram visualization |
| Chart.js | Latest | Dashboard charts and graphs |
| Laravel Wayfinder | 0.x | TypeScript route generation |

## Development & Build Tools

| Technology | Version | Purpose |
|------------|---------|---------|
| Vite | Latest | Frontend build tool and dev server |
| @laravel/vite-plugin-wayfinder | 0.x | Wayfinder Vite integration |
| ESLint | 9.x | JavaScript/TypeScript linting |
| Prettier | 3.x | Code formatting |
| Laravel Pint | 1.x | PHP code style fixer |

## Testing

| Technology | Version | Purpose |
|------------|---------|---------|
| Pest | 4.x | PHP testing framework |
| PHPUnit | 12.x | PHP unit testing (Pest backend) |
| Laravel Sail | 1.x | Docker development environment |

## Infrastructure & DevOps

| Technology | Purpose |
|------------|---------|
| Laravel Herd | Local development server |
| Docker (via Sail) | Containerized development environment |
| S3-Compatible Storage | Production file storage |
| Redis (optional) | Caching and queue backend |

## Key Architectural Decisions

### Inertia.js for SPA Experience
The application uses Inertia.js to provide a single-page application experience while maintaining Laravel's server-side routing and controllers. This eliminates the need for a separate API layer for the frontend while still enabling rich, reactive interfaces.

### Spatie Laravel-Permission for RBAC
Role-based access control is implemented using Spatie's Laravel-Permission package, providing a proven solution for managing roles (Administrator, IT Manager, Operator, Auditor, Viewer) and granular permissions.

### Visual Components
- **Rack Elevation Diagrams:** Custom Vue components with drag-and-drop support
- **Connection Diagrams:** D3.js or Vue Flow for interactive connection visualization
- **Dashboard Charts:** Chart.js for capacity and metrics visualization

### Real-Time Updates
Laravel Echo with Pusher/Soketi enables real-time updates for collaborative scenarios where multiple operators may be documenting infrastructure changes simultaneously.

### File Handling
- **Implementation Files:** Stored via Laravel Storage with S3 compatibility for production
- **PDF Generation:** Laravel DomPDF for audit reports and documentation
- **Import/Export:** Laravel Excel for bulk data operations

## Package Installation Commands

```bash
# Backend packages
composer require spatie/laravel-permission
composer require maatwebsite/excel
composer require barryvdh/laravel-dompdf
composer require pusher/pusher-php-server

# Frontend packages
npm install pinia
npm install d3
npm install chart.js vue-chartjs
```

## Database Schema Considerations

The application uses MySQL with the following key entity relationships:

- **Datacenters** contain **Rooms**
- **Rooms** contain **Racks** organized in **Rows**
- **Racks** contain **Devices** at specific U positions
- **Devices** have **Ports** of various types
- **Connections** link two **Ports** together
- **Implementation Files** define **Expected Connections**
- **Audits** compare **Expected Connections** against actual **Connections**
- **Findings** track discrepancies discovered during **Audits**
- **Activity Logs** track all user actions across all entities

## Configuration Files

| File | Purpose |
|------|---------|
| `config/fortify.php` | Authentication features configuration |
| `config/permission.php` | Spatie permission configuration |
| `config/excel.php` | Laravel Excel configuration |
| `config/dompdf.php` | PDF generation configuration |
| `config/broadcasting.php` | Real-time broadcasting configuration |
| `vite.config.js` | Frontend build configuration |
| `tailwind.config.js` | Tailwind CSS configuration (if needed for v4) |
| `eslint.config.js` | ESLint configuration |
| `.prettierrc` | Prettier configuration |
