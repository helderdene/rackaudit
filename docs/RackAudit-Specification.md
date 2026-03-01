# RackAudit

## Datacenter Management & Auditing System

**Technical Specification Document**

Version 1.0 | December 2025

Prepared by: HDSystem

---

## 1. Executive Summary

RackAudit is a comprehensive datacenter management and auditing system designed for small to medium enterprise datacenters. The system provides complete visibility into physical infrastructure including racks, servers, network equipment, and port connections.

The primary objective is to enable datacenter operators to maintain accurate documentation of their infrastructure and audit port connections against implementation specifications. This ensures that physical connections match documented configurations, reducing troubleshooting time and preventing configuration drift.

### 1.1 Key Objectives

- Provide real-time visibility into datacenter infrastructure inventory
- Enable auditing of port connections against implementation documentation
- Track changes and maintain historical records of infrastructure modifications
- Generate comprehensive audit reports for compliance and operational purposes
- Streamline datacenter operations through intuitive visual management tools

### 1.2 Target Users

- **Datacenter Operators:** Day-to-day infrastructure management and auditing
- **IT Managers:** Oversight, reporting, and capacity planning
- **Auditors:** Compliance verification and audit trail review

---

## 2. System Overview

### 2.1 Technology Stack

| Component | Technology |
|-----------|------------|
| Backend Framework | Laravel 11.x (PHP 8.2+) |
| Frontend Framework | Vue.js 3.x with Composition API |
| CSS Framework | Tailwind CSS 3.x |
| Database | MySQL 8.0 / PostgreSQL 15+ |
| State Management | Pinia |
| API Architecture | RESTful API with Laravel Sanctum |
| Real-time Updates | Laravel Echo + Pusher/Soketi |
| File Storage | Laravel Storage (S3 compatible) |

### 2.2 System Architecture

The system follows a modular monolithic architecture with clear separation between the API backend and SPA frontend. This approach provides the simplicity of a monolith while maintaining clean boundaries for future scaling if needed.

#### 2.2.1 Backend Structure

- Domain-driven organization with dedicated modules for each functional area
- Repository pattern for database abstraction
- Service layer for business logic encapsulation
- Event-driven architecture for audit logging and notifications
- Queue-based processing for import/export operations

#### 2.2.2 Frontend Structure

- Component-based architecture with reusable UI elements
- Composables for shared logic (authentication, notifications, etc.)
- Type-safe development with TypeScript
- Lazy-loaded routes for optimal performance

---

## 3. User Roles & Permissions

The system implements role-based access control (RBAC) with granular permissions for each module.

### 3.1 Role Definitions

| Role | Description | Access Level |
|------|-------------|--------------|
| Administrator | Full system access including user management and system configuration | All modules, all operations |
| IT Manager | Oversight of datacenter operations, reporting, and approval workflows | View all, edit assets, approve changes, generate reports |
| Operator | Day-to-day infrastructure management and auditing tasks | CRUD on assets, perform audits, limited reports |
| Auditor | Read-only access for compliance verification | View all, generate audit reports, no modifications |
| Viewer | Basic read-only access to infrastructure data | View assigned datacenters only |

### 3.2 Permission Matrix

Permissions are organized by module with four operation types: Create, Read, Update, Delete (CRUD). Additional special permissions include Export, Import, Audit, and Approve.

---

## 4. Core Modules

### 4.1 Datacenter Management

The foundational module for managing datacenter locations and their physical characteristics.

#### 4.1.1 Features

- Multi-datacenter support with hierarchical organization
- Floor plan visualization with interactive layouts
- Environmental zones definition (hot aisle, cold aisle, etc.)
- Power and cooling capacity tracking
- Contact information and access procedures

#### 4.1.2 Data Model

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| name | String(100) | Datacenter name |
| code | String(20) | Short code for labeling (e.g., DC-MNL-01) |
| address | Text | Physical address |
| total_power_kw | Decimal | Total power capacity in kilowatts |
| total_cooling_kw | Decimal | Total cooling capacity |
| floor_count | Integer | Number of floors |
| status | Enum | active, maintenance, decommissioned |
| metadata | JSON | Additional custom attributes |

### 4.2 Room/Zone Management

Manages physical spaces within datacenters including server rooms, network rooms, and support areas.

#### 4.2.1 Features

- Room layout with grid-based positioning
- Row and aisle organization
- Power distribution unit (PDU) assignment
- Environmental monitoring integration points
- Access control zone definitions

#### 4.2.2 Data Model

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| datacenter_id | UUID (FK) | Parent datacenter |
| name | String(100) | Room/zone name |
| code | String(20) | Short code (e.g., SR-01) |
| floor | Integer | Floor number |
| type | Enum | server_room, network_room, storage_room, support |
| grid_rows | Integer | Number of rows in grid layout |
| grid_cols | Integer | Number of columns in grid layout |
| power_capacity_kw | Decimal | Power capacity for this room |

### 4.3 Rack Management

Central module for managing server racks, their contents, and physical organization.

#### 4.3.1 Features

- Visual rack elevation diagrams with drag-and-drop equipment placement
- Front and rear view visualization
- U-space tracking and availability
- Power consumption monitoring per rack
- Weight capacity tracking
- Cable management documentation
- QR code generation for physical rack labeling

#### 4.3.2 Data Model

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| room_id | UUID (FK) | Parent room |
| name | String(50) | Rack identifier (e.g., R01-A05) |
| row_position | String(10) | Row identifier (e.g., A, B, C) |
| column_position | Integer | Position in row |
| total_u | Integer | Total rack units (typically 42 or 48) |
| used_u | Integer | Calculated: occupied U spaces |
| max_power_kw | Decimal | Maximum power capacity |
| current_power_kw | Decimal | Current power draw |
| max_weight_kg | Decimal | Maximum weight capacity |
| current_weight_kg | Decimal | Current equipment weight |
| status | Enum | available, in_use, reserved, maintenance |

### 4.4 Asset/Device Management

Comprehensive inventory management for all datacenter equipment including servers, switches, routers, storage arrays, and other devices.

#### 4.4.1 Features

- Complete asset lifecycle tracking (procurement to decommission)
- Hardware specifications and configurations
- Warranty and support contract tracking
- Asset tagging with barcode/QR code support
- Relationship mapping (parent/child devices)
- Custom attribute support for device-specific properties
- Bulk import/export capabilities

#### 4.4.2 Device Types

| Type | Examples |
|------|----------|
| Server | Rack servers, blade servers, tower servers |
| Network | Switches, routers, firewalls, load balancers |
| Storage | SAN arrays, NAS devices, tape libraries |
| Power | PDUs, UPS units, power strips |
| Infrastructure | KVM switches, console servers, environmental sensors |
| Other | Custom device types as needed |

#### 4.4.3 Data Model - Assets

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| rack_id | UUID (FK) | Installed rack (nullable for unracked) |
| asset_tag | String(50) | Unique asset identifier |
| serial_number | String(100) | Manufacturer serial number |
| hostname | String(255) | Device hostname |
| device_type | Enum | server, network, storage, power, other |
| manufacturer | String(100) | Equipment manufacturer |
| model | String(100) | Model number/name |
| u_height | Integer | Rack units consumed |
| u_position | Integer | Starting U position (bottom-up) |
| facing | Enum | front, rear |
| power_watts | Integer | Power consumption in watts |
| weight_kg | Decimal | Equipment weight |
| status | Enum | active, standby, maintenance, decommissioned |
| purchase_date | Date | Acquisition date |
| warranty_end | Date | Warranty expiration |
| specifications | JSON | Technical specs (CPU, RAM, storage, etc.) |
| notes | Text | Additional notes |

### 4.5 Port Management

Detailed tracking of all device ports including network, power, and console connections.

#### 4.5.1 Features

- Comprehensive port inventory per device
- Port type classification (network, power, console, etc.)
- Speed and protocol specifications
- Port status tracking (in use, available, reserved, disabled)
- Visual port mapping diagrams
- Bulk port creation from device templates

#### 4.5.2 Data Model - Ports

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| asset_id | UUID (FK) | Parent device |
| name | String(50) | Port identifier (e.g., eth0, Gi1/0/1) |
| port_type | Enum | ethernet, fiber, power, console, usb, sas, fc |
| speed | String(20) | Port speed (e.g., 1G, 10G, 25G, 100G) |
| connector_type | String(30) | RJ45, SFP, SFP+, QSFP, C13, C19, etc. |
| slot | String(20) | Physical slot/module location |
| position | Integer | Port position within slot |
| mac_address | String(17) | MAC address if applicable |
| status | Enum | available, connected, reserved, disabled, faulty |
| description | String(255) | Port description/purpose |

### 4.6 Connection Management

Tracks physical connections between ports, forming the backbone of the connection auditing system.

#### 4.6.1 Features

- Point-to-point connection tracking
- Cable type and specifications
- Cable labeling and color coding
- Connection path visualization
- Patch panel support
- Connection history tracking

#### 4.6.2 Data Model - Connections

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| source_port_id | UUID (FK) | Source port |
| destination_port_id | UUID (FK) | Destination port |
| cable_type | String(50) | CAT6, CAT6A, OM3, OM4, SMF, power, etc. |
| cable_color | String(30) | Cable jacket color |
| cable_length_m | Decimal | Cable length in meters |
| cable_label | String(50) | Cable identification label |
| status | Enum | active, planned, disconnected |
| installed_date | Date | Installation date |
| installed_by | UUID (FK) | User who installed |
| notes | Text | Additional notes |

### 4.7 Implementation Files

Central repository for storing and managing implementation documentation that defines expected configurations.

#### 4.7.1 Features

- Document upload and version control
- Structured data import from CSV/Excel
- Connection specification parsing
- Approval workflow for implementation files
- Revision history and comparison
- Template management for standard configurations

#### 4.7.2 Data Model - Implementation Files

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| name | String(255) | Document name |
| description | Text | Document description |
| file_path | String(500) | Storage path |
| file_type | Enum | csv, xlsx, pdf, json |
| version | String(20) | Version number |
| status | Enum | draft, pending_approval, approved, superseded |
| approved_by | UUID (FK) | Approving user |
| approved_at | Timestamp | Approval timestamp |
| effective_date | Date | When config becomes active |

#### 4.7.3 Data Model - Expected Connections

Parsed connection specifications from implementation files that define the expected state.

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| implementation_file_id | UUID (FK) | Source document |
| source_device_identifier | String(255) | Source device (hostname/asset tag) |
| source_port_name | String(50) | Source port identifier |
| dest_device_identifier | String(255) | Destination device |
| dest_port_name | String(50) | Destination port |
| cable_type | String(50) | Expected cable type |
| purpose | String(255) | Connection purpose/VLAN |
| matched_connection_id | UUID (FK) | Matched actual connection |
| match_status | Enum | matched, mismatched, missing, extra |

### 4.8 Audit Module

The core auditing functionality that compares actual infrastructure state against expected configurations.

#### 4.8.1 Audit Types

- **Connection Audit:** Validates physical connections match implementation files
- **Inventory Audit:** Verifies physical assets match system records
- **Port Audit:** Checks port configurations and status
- **Compliance Audit:** Validates against organizational standards

#### 4.8.2 Features

- Automated discrepancy detection between expected and actual connections
- Visual diff highlighting for mismatches
- Audit scheduling (one-time or recurring)
- Audit scope definition (full datacenter, room, rack, or device level)
- Discrepancy categorization and severity levels
- Resolution workflow with status tracking
- Audit history and trend analysis
- Export audit results to PDF/Excel

#### 4.8.3 Data Model - Audits

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| name | String(255) | Audit name/title |
| audit_type | Enum | connection, inventory, port, compliance |
| scope_type | Enum | datacenter, room, rack, device |
| scope_id | UUID | ID of scoped entity |
| implementation_file_id | UUID (FK) | Reference implementation file |
| status | Enum | pending, in_progress, completed, cancelled |
| started_at | Timestamp | Audit start time |
| completed_at | Timestamp | Audit completion time |
| performed_by | UUID (FK) | Auditor user |
| total_items | Integer | Total items audited |
| passed_items | Integer | Items matching expected state |
| failed_items | Integer | Items with discrepancies |
| notes | Text | Audit notes |

#### 4.8.4 Data Model - Audit Findings

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| audit_id | UUID (FK) | Parent audit |
| finding_type | Enum | missing, extra, mismatch, verified |
| severity | Enum | critical, major, minor, info |
| entity_type | String(50) | connection, port, asset |
| entity_id | UUID | Related entity ID |
| expected_value | JSON | Expected configuration |
| actual_value | JSON | Actual configuration |
| description | Text | Finding description |
| resolution_status | Enum | open, in_progress, resolved, accepted |
| resolved_by | UUID (FK) | User who resolved |
| resolved_at | Timestamp | Resolution timestamp |
| resolution_notes | Text | Resolution details |

### 4.9 Reporting Module

Comprehensive reporting capabilities for operational and compliance needs.

#### 4.9.1 Standard Reports

- **Infrastructure Summary:** Overview of all datacenters, racks, and assets
- **Capacity Report:** Current utilization and available capacity
- **Audit Summary:** Audit results and discrepancy trends
- **Connection Report:** All connections with status
- **Asset Inventory:** Complete asset listing with specifications
- **Warranty Report:** Assets by warranty status
- **Change History:** Recent infrastructure changes

#### 4.9.2 Features

- Customizable report parameters (date range, scope, filters)
- Multiple export formats (PDF, Excel, CSV)
- Scheduled report generation and email delivery
- Report templates for recurring needs
- Dashboard widgets for real-time metrics

### 4.10 Activity Logging

Comprehensive audit trail for all system activities.

#### 4.10.1 Logged Events

- User authentication (login, logout, failed attempts)
- All CRUD operations on assets, connections, and configurations
- Audit execution and findings
- Implementation file uploads and approvals
- Report generation
- System configuration changes

#### 4.10.2 Data Model - Activity Logs

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary identifier |
| user_id | UUID (FK) | Acting user |
| action | String(50) | Action type (create, update, delete, etc.) |
| entity_type | String(50) | Affected entity type |
| entity_id | UUID | Affected entity ID |
| old_values | JSON | Previous state |
| new_values | JSON | New state |
| ip_address | String(45) | Client IP address |
| user_agent | String(255) | Client user agent |
| created_at | Timestamp | Event timestamp |

---

## 5. User Interface Design

### 5.1 Design Principles

- Clean, professional interface suitable for technical users
- Responsive design for desktop and tablet use
- Consistent navigation and interaction patterns
- Visual hierarchy emphasizing critical information
- Accessibility compliance (WCAG 2.1 AA)

### 5.2 Key Views

#### 5.2.1 Dashboard

At-a-glance overview of datacenter health and pending actions.

- Summary cards: Total assets, racks, open audit findings
- Recent audit results with pass/fail indicators
- Capacity utilization charts
- Quick action buttons for common tasks
- Recent activity feed

#### 5.2.2 Rack Elevation View

Visual representation of rack contents with interactive elements.

- Front and rear view toggle
- Color-coded devices by type or status
- Drag-and-drop device placement
- Click-through to device details
- Port connection indicators
- Empty U-space highlighting

#### 5.2.3 Connection Diagram

Visual mapping of connections between devices.

- Interactive network topology view
- Filter by connection type, status, or path
- Trace connection paths end-to-end
- Highlight audit discrepancies
- Export diagram as image

#### 5.2.4 Audit Execution View

Guided interface for performing audits.

- Step-by-step audit workflow
- Side-by-side expected vs. actual comparison
- Quick-mark buttons for verified/discrepancy
- Photo attachment for physical verification
- Progress indicator and save draft capability

#### 5.2.5 Audit Results View

Comprehensive display of audit findings.

- Summary statistics and charts
- Filterable findings list
- Severity-based color coding
- Drill-down to specific discrepancies
- Resolution workflow actions
- Export and share options

---

## 6. API Structure

RESTful API endpoints following Laravel conventions with consistent response formats.

### 6.1 Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/auth/login | User authentication |
| POST | /api/auth/logout | End session |
| GET | /api/auth/user | Get current user |
| POST | /api/auth/refresh | Refresh token |

### 6.2 Resource Endpoints

Standard CRUD endpoints for each resource following REST conventions:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/{resource} | List with pagination |
| POST | /api/{resource} | Create new |
| GET | /api/{resource}/{id} | Get single |
| PUT | /api/{resource}/{id} | Update |
| DELETE | /api/{resource}/{id} | Delete |

Resources: datacenters, rooms, racks, assets, ports, connections, implementation-files, audits, users

### 6.3 Specialized Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/racks/{id}/elevation | Rack elevation data |
| GET | /api/assets/{id}/ports | Asset ports list |
| GET | /api/assets/{id}/connections | Asset connections |
| POST | /api/implementation-files/{id}/parse | Parse file contents |
| POST | /api/implementation-files/{id}/approve | Approve file |
| POST | /api/audits/{id}/start | Start audit execution |
| POST | /api/audits/{id}/complete | Complete audit |
| GET | /api/audits/{id}/findings | List audit findings |
| POST | /api/findings/{id}/resolve | Resolve finding |
| GET | /api/reports/{type} | Generate report |
| GET | /api/search | Global search |

---

## 7. Implementation Plan

### 7.1 Development Phases

#### Phase 1: Foundation (4 weeks)

- Project setup and development environment
- Authentication and authorization system
- User management module
- Base UI components and layouts
- Database schema implementation

#### Phase 2: Core Infrastructure (6 weeks)

- Datacenter and room management
- Rack management with elevation view
- Asset/device management
- Port management
- Bulk import/export functionality

#### Phase 3: Connections & Files (4 weeks)

- Connection management
- Implementation file upload and parsing
- Expected connection mapping
- Connection visualization

#### Phase 4: Audit System (5 weeks)

- Audit creation and configuration
- Audit execution workflow
- Discrepancy detection engine
- Finding management and resolution
- Audit reporting

#### Phase 5: Reporting & Polish (3 weeks)

- Dashboard implementation
- Report generation
- Activity logging
- Performance optimization
- Bug fixes and refinements

**Total Estimated Duration: 22 weeks (approximately 5.5 months)**

### 7.2 Technology Dependencies

| Package/Library | Purpose |
|-----------------|---------|
| Laravel Sanctum | API authentication |
| Spatie Laravel-Permission | Role-based access control |
| Laravel Excel | Excel import/export |
| Laravel DomPDF / Snappy | PDF generation |
| Intervention Image | Image processing |
| Vue Router | SPA routing |
| Pinia | State management |
| VueUse | Utility composables |
| Headless UI / Radix Vue | Accessible UI components |
| D3.js or Vue Flow | Connection diagrams |
| Chart.js or ApexCharts | Dashboard charts |

---

## 8. Cost Estimation

### 8.1 Development Costs

Based on 22 weeks of development with a small team:

| Item | Duration/Qty | Estimate |
|------|--------------|----------|
| Full-stack Developer | 22 weeks | ₱440,000 - ₱660,000 |
| UI/UX Design | 4 weeks | ₱80,000 - ₱120,000 |
| Project Management | 22 weeks (part-time) | ₱110,000 - ₱165,000 |
| QA/Testing | 6 weeks | ₱90,000 - ₱120,000 |
| Documentation | 2 weeks | ₱30,000 - ₱50,000 |

**Total Development Estimate: ₱750,000 - ₱1,115,000**

### 8.2 Infrastructure Costs (Monthly)

| Item | Monthly Cost |
|------|--------------|
| VPS/Cloud Server (Production) | ₱2,500 - ₱5,000 |
| Database Server (if separate) | ₱1,500 - ₱3,000 |
| File Storage (S3-compatible) | ₱500 - ₱1,500 |
| SSL Certificate | Free (Let's Encrypt) |
| Backup Storage | ₱500 - ₱1,000 |
| Monitoring (optional) | ₱0 - ₱1,500 |

**Total Monthly Infrastructure: ₱5,000 - ₱12,000**

### 8.3 Licensing Considerations

All core technologies (Laravel, Vue.js, MySQL/PostgreSQL) are open-source with permissive licenses suitable for commercial use. No significant licensing costs are anticipated for the base system.

---

## 9. Future Enhancements

Potential features for future versions:

### 9.1 Integration Capabilities

- SNMP integration for automated device discovery
- IPMI/iLO/DRAC integration for server management
- LDAP/Active Directory authentication
- Ticketing system integration (e.g., GLPI, ServiceNow)
- Monitoring platform integration (Zabbix, Nagios, PRTG)

### 9.2 Advanced Features

- Mobile application for field auditing
- Barcode/QR code scanning for asset verification
- Automated change detection via network scanning
- Capacity planning with predictive analytics
- 3D datacenter visualization
- Multi-tenant SaaS deployment option

### 9.3 Compliance & Standards

- ISO 27001 compliance reporting templates
- SOC 2 audit trail enhancements
- Custom compliance framework support

---

## 10. Appendix

### 10.1 Glossary

| Term | Definition |
|------|------------|
| U (Rack Unit) | Standard unit of measure for rack space; 1U = 1.75 inches (44.45mm) |
| PDU | Power Distribution Unit - distributes power to rack equipment |
| Patch Panel | Panel with ports for organizing and connecting cables |
| Implementation File | Document defining expected infrastructure configuration |
| Finding | Discrepancy discovered during an audit |
| Elevation View | Front or rear visual representation of rack contents |

### 10.2 Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | Dec 2025 | HDSystem | Initial specification |

---

*— End of Document —*
