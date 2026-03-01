<?php

namespace App\Http\Requests;

use App\Enums\PortDirection;
use App\Enums\PortStatus;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use App\Enums\PortVisualFace;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePortRequest extends FormRequest
{
    /**
     * Roles that can manage ports on devices.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only users who can update the parent device can create ports.
     */
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        // Check if user has the required role to update devices
        return $this->user()->hasAnyRole(self::AUTHORIZED_ROLES);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Required fields
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PortType::class)],
            'subtype' => ['required', Rule::enum(PortSubtype::class)],

            // Optional fields with defaults
            'status' => ['nullable', Rule::enum(PortStatus::class)],
            'direction' => ['nullable', Rule::enum(PortDirection::class)],

            // Physical position fields
            'position_slot' => ['nullable', 'integer', 'min:0'],
            'position_row' => ['nullable', 'integer', 'min:0'],
            'position_column' => ['nullable', 'integer', 'min:0'],

            // Visual position fields
            'visual_x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'visual_y' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'visual_face' => ['nullable', Rule::enum(PortVisualFace::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $this->validateSubtypeMatchesType($validator);
        });
    }

    /**
     * Validate that the subtype is valid for the given type.
     */
    private function validateSubtypeMatchesType(\Illuminate\Validation\Validator $validator): void
    {
        $type = $this->input('type');
        $subtype = $this->input('subtype');

        if (! $type || ! $subtype) {
            return;
        }

        // Get the PortType enum instance
        $portType = PortType::tryFrom($type);
        if (! $portType) {
            return;
        }

        // Get the PortSubtype enum instance
        $portSubtype = PortSubtype::tryFrom($subtype);
        if (! $portSubtype) {
            return;
        }

        // Check if the subtype is valid for the given type
        $validSubtypes = PortSubtype::forType($portType);
        if (! in_array($portSubtype, $validSubtypes, true)) {
            $validator->errors()->add(
                'subtype',
                "The selected subtype is not valid for the {$portType->label()} port type."
            );
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
            'label.required' => 'The port label is required.',
            'label.string' => 'The port label must be a string.',
            'label.max' => 'The port label must not exceed 255 characters.',
            'type.required' => 'The port type is required.',
            'type.Illuminate\Validation\Rules\Enum' => 'The selected port type is invalid.',
            'subtype.required' => 'The port subtype is required.',
            'subtype.Illuminate\Validation\Rules\Enum' => 'The selected port subtype is invalid.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected port status is invalid.',
            'direction.Illuminate\Validation\Rules\Enum' => 'The selected port direction is invalid.',
            'position_slot.integer' => 'The position slot must be an integer.',
            'position_slot.min' => 'The position slot must be at least 0.',
            'position_row.integer' => 'The position row must be an integer.',
            'position_row.min' => 'The position row must be at least 0.',
            'position_column.integer' => 'The position column must be an integer.',
            'position_column.min' => 'The position column must be at least 0.',
            'visual_x.numeric' => 'The visual X position must be a number.',
            'visual_x.min' => 'The visual X position must be at least 0.',
            'visual_x.max' => 'The visual X position must not exceed 100.',
            'visual_y.numeric' => 'The visual Y position must be a number.',
            'visual_y.min' => 'The visual Y position must be at least 0.',
            'visual_y.max' => 'The visual Y position must not exceed 100.',
            'visual_face.Illuminate\Validation\Rules\Enum' => 'The selected visual face is invalid.',
        ];
    }
}
