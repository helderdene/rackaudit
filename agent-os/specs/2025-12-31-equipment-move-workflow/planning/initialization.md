# Equipment Move Workflow

## Initial Feature Description

Equipment Move Workflow - Guided process for moving devices between racks with connection documentation and history.

## Source

From product roadmap item #44 (Phase 6: Polish & Optimization):
> Equipment Move Workflow - Guided process for moving devices between racks with connection documentation and history `M`

## Context

This feature builds upon the existing infrastructure management capabilities:
- Devices can be placed in racks at specific U positions (Device model with rack_id, start_u)
- Connections exist between ports (Connection model with source_port_id, destination_port_id)
- Activity logging tracks all changes (ActivityLog with old_values, new_values)
- Connection history is already tracked and viewable
- Rack elevation view supports drag-and-drop device placement within a single rack
