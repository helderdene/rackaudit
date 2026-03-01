# Performance Optimization Spec

## Initial Description

Performance Optimization - Query optimization, caching, and lazy loading for large datacenter installations.

## Context

This is for a Laravel 12 + Vue 3 + Inertia.js datacenter infrastructure management application (RackAudit). The app manages:
- Datacenters, rooms, racks, devices, ports
- Connections between ports
- Audits and findings
- Implementation files
- Real-time updates via Laravel Echo

The optimization needs to handle large installations with potentially thousands of racks, tens of thousands of devices, and hundreds of thousands of connections.

## Initial Codebase Analysis

### Current State Observations

**Query Patterns Identified:**
1. Dashboard performs multiple independent count queries for metrics with sparkline generation (7 historical queries each)
2. Deep relationship loading: `sourcePort.device.rack.row.room.datacenter` for connections
3. Rack utilization calculates by loading all racks with their devices
4. ConnectionController loads full hierarchy for filtering
5. AuditController performs multiple count queries with `whereHas` for progress stats
6. SearchService likely performs broad queries across multiple entity types
7. DeviceController loads hierarchical filter options (all datacenters, rooms, rows, racks)

**Potential N+1 Issues:**
- Rack index: `$rack->pdus()->count()` in loop
- Device show: Loading ports with connection relationships
- Dashboard: Loading devices per rack for utilization calculation
- ConnectionDiagram: Loading full device hierarchy for each connection

**No Caching Currently:**
- No `Cache::` usage found in app directory
- Redis is listed as optional in tech stack but not currently used
- All data loaded fresh on each request

**Heavy Data Loading Pages:**
- Dashboard (metrics, charts, activity feed)
- Connection diagram (all connections with full hierarchy)
- Rack elevation (devices, ports)
- Audit execution (verification items)
- Search results (multi-entity search)
- Device show (ports with connections)

**Areas for Lazy Loading:**
- Dashboard chart data (could be deferred)
- Connection diagram (large datasets)
- Audit verification items
- Activity logs
