<?php

namespace App\Http\Requests;

use App\Enums\DeviceLifecycleStatus;
use App\Enums\PortStatus;
use App\Enums\PortType;
use App\Enums\RackStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request validation for search API endpoints.
 *
 * Validates search query and filter parameters including hierarchical
 * location filters and entity-specific attribute filters.
 */
class SearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * All authenticated users can perform searches; RBAC filtering
     * is applied at the service level to limit results.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Search query
            'q' => ['nullable', 'string', 'max:255'],

            // Entity type filter
            'type' => ['nullable', 'string', Rule::in(['datacenters', 'racks', 'devices', 'ports', 'connections'])],

            // Hierarchical location filters
            'datacenter_id' => ['nullable', 'integer', 'exists:datacenters,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'row_id' => ['nullable', 'integer', 'exists:rows,id'],
            'rack_id' => ['nullable', 'integer', 'exists:racks,id'],

            // Entity-specific attribute filters
            'lifecycle_status' => ['nullable', 'string', Rule::enum(DeviceLifecycleStatus::class)],
            'port_type' => ['nullable', 'string', Rule::enum(PortType::class)],
            'port_status' => ['nullable', 'string', Rule::enum(PortStatus::class)],
            'rack_status' => ['nullable', 'string', Rule::enum(RackStatus::class)],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'The search query must not exceed 255 characters.',
            'type.in' => 'The entity type must be one of: datacenters, racks, devices, ports, connections.',
            'datacenter_id.integer' => 'The datacenter ID must be an integer.',
            'datacenter_id.exists' => 'The selected datacenter does not exist.',
            'room_id.integer' => 'The room ID must be an integer.',
            'room_id.exists' => 'The selected room does not exist.',
            'row_id.integer' => 'The row ID must be an integer.',
            'row_id.exists' => 'The selected row does not exist.',
            'rack_id.integer' => 'The rack ID must be an integer.',
            'rack_id.exists' => 'The selected rack does not exist.',
            'lifecycle_status.enum' => 'The lifecycle status must be a valid device lifecycle status.',
            'port_type.enum' => 'The port type must be a valid port type.',
            'port_status.enum' => 'The port status must be a valid port status.',
            'rack_status.enum' => 'The rack status must be a valid rack status.',
        ];
    }

    /**
     * Get the validated filters array for the SearchService.
     *
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        $filters = [];

        // Hierarchical location filters
        if ($this->filled('datacenter_id')) {
            $filters['datacenter_id'] = (int) $this->input('datacenter_id');
        }
        if ($this->filled('room_id')) {
            $filters['room_id'] = (int) $this->input('room_id');
        }
        if ($this->filled('row_id')) {
            $filters['row_id'] = (int) $this->input('row_id');
        }
        if ($this->filled('rack_id')) {
            $filters['rack_id'] = (int) $this->input('rack_id');
        }

        // Entity-specific attribute filters
        if ($this->filled('lifecycle_status')) {
            $filters['lifecycle_status'] = $this->input('lifecycle_status');
        }
        if ($this->filled('port_type')) {
            $filters['port_type'] = $this->input('port_type');
        }
        if ($this->filled('port_status')) {
            $filters['port_status'] = $this->input('port_status');
        }
        if ($this->filled('rack_status')) {
            $filters['rack_status'] = $this->input('rack_status');
        }

        return $filters;
    }

    /**
     * Get the search query string.
     */
    public function getSearchQuery(): string
    {
        return $this->input('q', '');
    }

    /**
     * Get the entity type filter, if specified.
     */
    public function getEntityType(): ?string
    {
        return $this->input('type');
    }
}
