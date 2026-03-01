<?php

namespace App\Http\Requests;

use App\Enums\AuditScopeType;
use App\Enums\AuditType;
use App\Models\Datacenter;
use App\Models\Device;
use App\Models\Rack;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAuditRequest extends FormRequest
{
    /**
     * Roles that can create audits.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
        'Auditor',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators, IT Managers, and Auditors can create audits.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Audit metadata
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],

            // Audit type
            'type' => ['required', Rule::enum(AuditType::class)],

            // Scope configuration
            'scope_type' => ['required', Rule::enum(AuditScopeType::class)],
            'datacenter_id' => ['required', 'exists:datacenters,id'],
            'room_id' => ['required_if:scope_type,room', 'nullable', 'exists:rooms,id'],

            // Rack selection (for racks scope)
            'rack_ids' => ['required_if:scope_type,racks', 'nullable', 'array'],
            'rack_ids.*' => ['exists:racks,id'],

            // Device selection (optional, only for racks scope)
            'device_ids' => ['nullable', 'array'],
            'device_ids.*' => ['exists:devices,id'],

            // Assignees
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['exists:users,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateConnectionAuditHasApprovedImplementationFile($validator);
            $this->validateDeviceIdsOnlyWithRacksScope($validator);
            $this->validateRoomBelongsToDatacenter($validator);
            $this->validateRacksBelongToDatacenter($validator);
            $this->validateDevicesBelongToSelectedRacks($validator);
        });
    }

    /**
     * Validate that connection audits have an approved implementation file.
     */
    protected function validateConnectionAuditHasApprovedImplementationFile(Validator $validator): void
    {
        // Only validate for connection audits
        if ($this->input('type') !== AuditType::Connection->value) {
            return;
        }

        // Skip if datacenter_id is missing or invalid (other validators will catch this)
        $datacenterId = $this->input('datacenter_id');
        if (! $datacenterId) {
            return;
        }

        $datacenter = Datacenter::find($datacenterId);
        if (! $datacenter) {
            return;
        }

        // Check for approved implementation files
        if (! $datacenter->hasApprovedImplementationFiles()) {
            $validator->errors()->add(
                'type',
                'Connection audits require an approved implementation file. Please upload and approve an implementation file for this datacenter first. You can manage implementation files on the Implementation Files page.'
            );
        }
    }

    /**
     * Validate that device_ids is only provided when scope_type is racks.
     */
    protected function validateDeviceIdsOnlyWithRacksScope(Validator $validator): void
    {
        $scopeType = $this->input('scope_type');
        $deviceIds = $this->input('device_ids');

        // If device_ids is provided and not empty, scope_type must be racks
        if (! empty($deviceIds) && $scopeType !== AuditScopeType::Racks->value) {
            $validator->errors()->add(
                'device_ids',
                'Device selection is only available when the scope type is set to individual racks.'
            );
        }
    }

    /**
     * Validate that the selected room belongs to the selected datacenter.
     */
    protected function validateRoomBelongsToDatacenter(Validator $validator): void
    {
        $scopeType = $this->input('scope_type');
        $roomId = $this->input('room_id');
        $datacenterId = $this->input('datacenter_id');

        // Only validate for room scope
        if ($scopeType !== AuditScopeType::Room->value) {
            return;
        }

        // Skip if room_id or datacenter_id is missing (other validators will catch this)
        if (! $roomId || ! $datacenterId) {
            return;
        }

        $room = Room::find($roomId);
        if (! $room) {
            return;
        }

        if ($room->datacenter_id !== (int) $datacenterId) {
            $validator->errors()->add(
                'room_id',
                'The selected room does not belong to the selected datacenter.'
            );
        }
    }

    /**
     * Validate that all selected racks belong to the selected datacenter.
     */
    protected function validateRacksBelongToDatacenter(Validator $validator): void
    {
        $scopeType = $this->input('scope_type');
        $rackIds = $this->input('rack_ids');
        $datacenterId = $this->input('datacenter_id');

        // Only validate for racks scope
        if ($scopeType !== AuditScopeType::Racks->value) {
            return;
        }

        // Skip if rack_ids or datacenter_id is missing (other validators will catch this)
        if (empty($rackIds) || ! $datacenterId) {
            return;
        }

        // Get all racks and check their datacenter through the hierarchy
        $racks = Rack::with('row.room.datacenter')
            ->whereIn('id', $rackIds)
            ->get();

        foreach ($racks as $rack) {
            if ($rack->row?->room?->datacenter_id !== (int) $datacenterId) {
                $validator->errors()->add(
                    'rack_ids',
                    'One or more selected racks do not belong to the selected datacenter.'
                );

                return;
            }
        }
    }

    /**
     * Validate that all selected devices belong to the selected racks.
     */
    protected function validateDevicesBelongToSelectedRacks(Validator $validator): void
    {
        $deviceIds = $this->input('device_ids');
        $rackIds = $this->input('rack_ids');

        // Skip if device_ids is empty or rack_ids is empty
        if (empty($deviceIds) || empty($rackIds)) {
            return;
        }

        // Get all devices and check their rack_id
        $devices = Device::whereIn('id', $deviceIds)->get();

        foreach ($devices as $device) {
            if (! in_array($device->rack_id, array_map('intval', $rackIds))) {
                $validator->errors()->add(
                    'device_ids',
                    'One or more selected devices do not belong to the selected racks.'
                );

                return;
            }
        }
    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Audit metadata
            'name.required' => 'The audit name is required.',
            'name.string' => 'The audit name must be text.',
            'name.max' => 'The audit name must not exceed 255 characters.',
            'description.string' => 'The description must be text.',
            'description.max' => 'The description must not exceed 1000 characters.',
            'due_date.required' => 'The due date is required.',
            'due_date.date' => 'Please enter a valid date for the due date.',
            'due_date.after_or_equal' => 'The due date must be today or a future date.',

            // Audit type
            'type.required' => 'Please select an audit type.',
            'type.Illuminate\Validation\Rules\Enum' => 'Please select a valid audit type (connection or inventory).',

            // Scope configuration
            'scope_type.required' => 'Please select a scope type.',
            'scope_type.Illuminate\Validation\Rules\Enum' => 'Please select a valid scope type (datacenter, room, or racks).',
            'datacenter_id.required' => 'Please select a datacenter.',
            'datacenter_id.exists' => 'The selected datacenter does not exist.',
            'room_id.required_if' => 'Please select a room when the scope type is room.',
            'room_id.exists' => 'The selected room does not exist.',

            // Rack selection
            'rack_ids.required_if' => 'Please select at least one rack when the scope type is racks.',
            'rack_ids.array' => 'The rack selection must be a list.',
            'rack_ids.*.exists' => 'One or more of the selected racks does not exist.',

            // Device selection
            'device_ids.array' => 'The device selection must be a list.',
            'device_ids.*.exists' => 'One or more of the selected devices does not exist.',

            // Assignees
            'assignee_ids.required' => 'Please assign at least one user to this audit.',
            'assignee_ids.array' => 'The assignee selection must be a list.',
            'assignee_ids.min' => 'Please assign at least one user to this audit.',
            'assignee_ids.*.exists' => 'One or more of the selected assignees does not exist.',
        ];
    }
}
