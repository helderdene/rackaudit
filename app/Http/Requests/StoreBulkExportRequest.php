<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for validating bulk export creation.
 *
 * Validates the entity type, format selection, and optional
 * hierarchical filter parameters.
 */
class StoreBulkExportRequest extends FormRequest
{
    /**
     * Roles that can perform bulk exports.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Entity types that can be exported (excludes Mixed).
     *
     * @var array<string>
     */
    private const EXPORTABLE_ENTITY_TYPES = [
        'datacenter',
        'room',
        'row',
        'rack',
        'device',
        'port',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can perform bulk exports.
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
            'entity_type' => [
                'required',
                Rule::in(self::EXPORTABLE_ENTITY_TYPES),
            ],
            'format' => [
                'required',
                Rule::in(['csv', 'xlsx']),
            ],
            'datacenter_id' => [
                'nullable',
                'integer',
                'exists:datacenters,id',
            ],
            'room_id' => [
                'nullable',
                'integer',
                'exists:rooms,id',
            ],
            'row_id' => [
                'nullable',
                'integer',
                'exists:rows,id',
            ],
            'rack_id' => [
                'nullable',
                'integer',
                'exists:racks,id',
            ],
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
            'entity_type.required' => 'Please select an entity type to export.',
            'entity_type.in' => 'The selected entity type is invalid.',
            'format.required' => 'Please select an export format.',
            'format.in' => 'The format must be either CSV or XLSX.',
            'datacenter_id.exists' => 'The selected datacenter does not exist.',
            'room_id.exists' => 'The selected room does not exist.',
            'row_id.exists' => 'The selected row does not exist.',
            'rack_id.exists' => 'The selected rack does not exist.',
        ];
    }

    /**
     * Get the validated filters as an array.
     *
     * @return array<string, int|null>
     */
    public function getFilters(): array
    {
        $filters = [];

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

        return $filters;
    }
}
