# Task Breakdown: Connection Management

## Overview
Total Tasks: 4 Task Groups with 28 sub-tasks

This feature enables CRUD operations for point-to-point connections between ports, tracking cable properties (type, length, color) and path notes, with validation for port compatibility and power directionality, plus support for patch panel port pairing to derive logical end-to-end paths.

## Task List

### Database Layer

#### Task Group 1: Data Models, Enums, and Migrations
**Dependencies:** None

- [x] 1.0 Complete database layer
  - [x] 1.1 Write 2-8 focused tests for Connection model and CableType enum
    - Test Connection model creation with valid data
    - Test Connection model relationships (sourcePort, destinationPort)
    - Test CableType enum `label()` method returns correct human-readable names
    - Test CableType enum `forPortType()` returns correct cable types for Ethernet, Fiber, Power
    - Test Port model `pairedPort()` relationship
    - Test Port model `connection()` relationship
  - [x] 1.2 Create CableType enum (`app/Enums/CableType.php`)
    - Values: `cat5e`, `cat6`, `cat6a`, `fiber_sm`, `fiber_mm`, `power_c13`, `power_c14`, `power_c19`, `power_c20`
    - `label()` method returning human-readable names (Cat5e, Cat6, Cat6a, Fiber SM, Fiber MM, C13, C14, C19, C20)
    - `forPortType(PortType $type)` method returning valid cable types for each port type
    - Follow pattern from existing `PortType` enum
  - [x] 1.3 Create migration for `connections` table
    - Fields: `id`, `source_port_id`, `destination_port_id`, `cable_type`, `cable_length`, `cable_color`, `path_notes`, `timestamps`, `deleted_at`
    - Foreign keys to `ports` table with cascade on delete
    - Indexes on `source_port_id` and `destination_port_id`
    - Run: `php artisan make:migration create_connections_table --no-interaction`
  - [x] 1.4 Create migration to add `paired_port_id` to `ports` table
    - Add nullable `paired_port_id` column
    - Foreign key to `ports` table (self-referential)
    - Run: `php artisan make:migration add_paired_port_id_to_ports_table --no-interaction`
  - [x] 1.5 Create Connection model (`app/Models/Connection.php`)
    - Run: `php artisan make:model Connection --factory --no-interaction`
    - Apply `Loggable` concern for activity logging
    - Use `SoftDeletes` trait
    - Fillable: `source_port_id`, `destination_port_id`, `cable_type`, `cable_length`, `cable_color`, `path_notes`
    - Casts: `cable_type` to `CableType` enum, `cable_length` to decimal
    - Relationships: `sourcePort()` belongsTo Port, `destinationPort()` belongsTo Port
    - Add `getLogicalPath()` method to traverse patch panel port pairs
  - [x] 1.6 Update Port model with new relationships
    - Add `pairedPort()` belongsTo self-referential relationship
    - Add `connectionAsSource()` hasOne relationship
    - Add `connectionAsDestination()` hasOne relationship
    - Add `connection()` accessor to get connection regardless of direction
  - [x] 1.7 Create ConnectionFactory for testing
    - Configure factory to create valid connections with source and destination ports
    - Add states for different cable types
  - [x] 1.8 Ensure database layer tests pass
    - Run ONLY the 2-8 tests written in 1.1
    - Verify migrations run successfully
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 1.1 pass
- CableType enum has all required values and methods
- Migrations create correct schema with indexes and foreign keys
- Connection model has proper relationships and Loggable concern
- Port model has pairedPort and connection relationships
- Factory creates valid Connection instances

### API Layer

#### Task Group 2: Connection CRUD API Endpoints
**Dependencies:** Task Group 1

- [x] 2.0 Complete API layer for connections
  - [x] 2.1 Write 2-8 focused tests for Connection API endpoints
    - Test `POST /connections` creates connection with valid data
    - Test `POST /connections` fails with incompatible port types
    - Test `POST /connections` fails with invalid power direction
    - Test `POST /connections` fails when port already has connection
    - Test `GET /connections` returns list with filtering
    - Test `PUT /connections/{connection}` updates cable properties
    - Test `DELETE /connections/{connection}` soft deletes connection
  - [x] 2.2 Create StoreConnectionRequest form request
    - Validate `source_port_id` exists in ports table
    - Validate `destination_port_id` exists in ports table
    - Validate `cable_type` is valid CableType enum value
    - Validate `cable_length` is positive decimal
    - Validate `cable_color` is string (max 50 chars)
    - Validate `path_notes` is nullable text
    - Custom rule: port compatibility (same PortType for network/fiber)
    - Custom rule: power directionality (source=Output, destination=Input)
    - Custom rule: ports not already connected
  - [x] 2.3 Create UpdateConnectionRequest form request
    - Validate `cable_type`, `cable_length`, `cable_color`, `path_notes`
    - Source and destination ports cannot be changed after creation
  - [x] 2.4 Create ConnectionResource for API responses
    - Include `id`, `cable_type`, `cable_length`, `cable_color`, `path_notes`, `timestamps`
    - Include `source_port` with device info (nested resource or inline)
    - Include `destination_port` with device info
    - Include `logical_path` array when patch panels are involved
    - Follow pattern from existing resources
  - [x] 2.5 Create ConnectionController
    - `index()`: List connections with filtering (by device, rack, port type)
    - `store()`: Create connection, update port statuses to Connected
    - `show()`: Return single connection with relationships
    - `update()`: Update cable properties only
    - `destroy()`: Soft delete connection, update port statuses to Available
    - Use Gate authorization following PortController pattern
  - [x] 2.6 Add connection routes to `routes/api.php`
    - `GET /connections` -> `ConnectionController@index`
    - `POST /connections` -> `ConnectionController@store`
    - `GET /connections/{connection}` -> `ConnectionController@show`
    - `PUT /connections/{connection}` -> `ConnectionController@update`
    - `DELETE /connections/{connection}` -> `ConnectionController@destroy`
  - [x] 2.7 Ensure Connection API tests pass
    - Run ONLY the 2-8 tests written in 2.1
    - Verify all CRUD operations work correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 2.1 pass
- All CRUD operations work correctly
- Port compatibility validation rejects incompatible connections
- Power directionality validation enforces Output->Input direction
- One-connection-per-port constraint is enforced
- Port status updates automatically on create/delete
- ConnectionResource includes logical path for patch panel connections

#### Task Group 3: Port Pairing API Endpoints
**Dependencies:** Task Group 1

- [x] 3.0 Complete API layer for port pairing
  - [x] 3.1 Write 2-8 focused tests for Port Pairing API
    - Test `POST /devices/{device}/ports/{port}/pair` creates bidirectional pairing
    - Test pairing fails when ports are on different devices
    - Test pairing fails when either port is already paired
    - Test `DELETE /devices/{device}/ports/{port}/pair` removes pairing from both ports
    - Test logical path traversal works through paired ports
  - [x] 3.2 Create PairPortRequest form request
    - Validate `paired_port_id` exists and belongs to same device
    - Validate neither port is already paired
    - Validate port is not being paired with itself
  - [x] 3.3 Add pair/unpair methods to PortController (or create dedicated controller)
    - `pair()`: Set bidirectional pairing (A->B and B->A)
    - `unpair()`: Remove pairing from both ports
    - Use database transaction for atomicity
    - Follow existing authorization patterns
  - [x] 3.4 Add port pairing routes to `routes/api.php`
    - `POST /devices/{device}/ports/{port}/pair` -> pair action
    - `DELETE /devices/{device}/ports/{port}/pair` -> unpair action
  - [x] 3.5 Update PortResource to include pairing info
    - Add `paired_port_id` to resource output
    - Add `paired_port` relationship when loaded
  - [x] 3.6 Ensure Port Pairing API tests pass
    - Run ONLY the 2-8 tests written in 3.1
    - Verify bidirectional pairing works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-8 tests written in 3.1 pass
- Bidirectional pairing creates/removes links on both ports atomically
- Validation prevents invalid pairings (different devices, already paired)
- PortResource shows pairing information

### Testing

#### Task Group 4: Test Review & Gap Analysis
**Dependencies:** Task Groups 1-3

- [x] 4.0 Review existing tests and fill critical gaps only
  - [x] 4.1 Review tests from Task Groups 1-3
    - Review the 2-8 tests written by database layer (Task 1.1)
    - Review the 2-8 tests written by API layer - connections (Task 2.1)
    - Review the 2-8 tests written by API layer - port pairing (Task 3.1)
    - Total existing tests: approximately 15-20 tests
  - [x] 4.2 Analyze test coverage gaps for THIS feature only
    - Identify critical user workflows that lack test coverage
    - Focus ONLY on gaps related to Connection Management feature
    - Prioritize end-to-end workflows: create connection -> verify path -> delete
    - Check edge cases: concurrent port connections, soft delete/restore scenarios
  - [x] 4.3 Write up to 10 additional strategic tests maximum
    - Test logical path derivation through multiple patch panels
    - Test port status transitions (Available -> Connected -> Available)
    - Test connection filtering by device, rack, port type
    - Test CableType.forPortType() returns correct types for all port types
    - Test soft deleted connections free up ports for new connections
    - Integration test: full workflow from port pairing to connection creation to path derivation
  - [x] 4.4 Run feature-specific tests only
    - Run ONLY tests related to Connection Management feature
    - Expected total: approximately 25-30 tests maximum
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 25-30 tests total)
- Critical user workflows for Connection Management are covered
- No more than 10 additional tests added when filling in testing gaps
- Testing focused exclusively on Connection Management feature requirements

## Execution Order

Recommended implementation sequence:
1. **Database Layer (Task Group 1)** - Create CableType enum, migrations, Connection model, update Port model
2. **Connection API Layer (Task Group 2)** - CRUD endpoints for connections with validation
3. **Port Pairing API Layer (Task Group 3)** - Endpoints for patch panel port pairing
4. **Test Review & Gap Analysis (Task Group 4)** - Review and fill critical test gaps

## Key Files to Create/Modify

### New Files
- `app/Enums/CableType.php` - Cable type enum with label() and forPortType() methods
- `app/Models/Connection.php` - Connection model with relationships and getLogicalPath()
- `app/Http/Controllers/ConnectionController.php` - CRUD controller
- `app/Http/Requests/StoreConnectionRequest.php` - Create validation with port compatibility rules
- `app/Http/Requests/UpdateConnectionRequest.php` - Update validation
- `app/Http/Requests/PairPortRequest.php` - Port pairing validation
- `app/Http/Resources/ConnectionResource.php` - API resource
- `database/migrations/xxxx_create_connections_table.php` - Connections schema
- `database/migrations/xxxx_add_paired_port_id_to_ports_table.php` - Port pairing column
- `database/factories/ConnectionFactory.php` - Test factory
- `tests/Feature/ConnectionTest.php` - Connection API tests
- `tests/Feature/PortPairingTest.php` - Port pairing tests
- `tests/Unit/CableTypeTest.php` - Enum tests

### Files to Modify
- `app/Models/Port.php` - Add pairedPort() and connection() relationships
- `app/Http/Resources/PortResource.php` - Add paired_port_id and paired_port
- `app/Http/Controllers/PortController.php` - Add pair/unpair methods (or create separate controller)
- `routes/api.php` - Add connection and port pairing routes

## Technical Notes

### Port Compatibility Validation Logic
```
Ethernet ports -> can only connect to Ethernet ports
Fiber ports -> can only connect to Fiber ports
Power ports -> source must be Output direction, destination must be Input direction
```

### Logical Path Derivation
The `getLogicalPath()` method should:
1. Start from source port
2. If source port has a paired port, follow the pair
3. Continue to destination port
4. If destination port has a paired port, follow the pair
5. Return array of all ports in the path

Example path: Server Port -> Patch Panel Front -> (paired) Patch Panel Back -> Switch Port
