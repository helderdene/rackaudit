<?php

namespace App\Http\Requests;

use App\Enums\PortDirection;
use App\Enums\PortSubtype;
use App\Enums\PortType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStorePortRequest extends FormRequest
{
    /**
     * Maximum number of ports that can be created in a single bulk operation.
     */
    private const MAX_PORTS_PER_BULK = 100;

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
     * Only users who can update the parent device can bulk create ports.
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
            // Template parameters
            'prefix' => ['required', 'string', 'max:50'],
            'start_number' => ['required', 'integer', 'min:1'],
            'end_number' => ['required', 'integer', 'min:1', 'gte:start_number'],

            // Port configuration
            'type' => ['required', Rule::enum(PortType::class)],
            'subtype' => ['required', Rule::enum(PortSubtype::class)],
            'direction' => ['nullable', Rule::enum(PortDirection::class)],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $this->validateSubtypeMatchesType($validator);
            $this->validatePortCountLimit($validator);
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
     * Validate that the number of ports to create does not exceed the limit.
     */
    private function validatePortCountLimit(\Illuminate\Validation\Validator $validator): void
    {
        $startNumber = $this->input('start_number');
        $endNumber = $this->input('end_number');

        if (! is_numeric($startNumber) || ! is_numeric($endNumber)) {
            return;
        }

        $portCount = (int) $endNumber - (int) $startNumber + 1;

        if ($portCount > self::MAX_PORTS_PER_BULK) {
            $validator->errors()->add(
                'end_number',
                'Cannot create more than '.self::MAX_PORTS_PER_BULK." ports at once. You are trying to create {$portCount} ports."
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
            'prefix.required' => 'The port label prefix is required.',
            'prefix.string' => 'The port label prefix must be a string.',
            'prefix.max' => 'The port label prefix must not exceed 50 characters.',
            'start_number.required' => 'The start number is required.',
            'start_number.integer' => 'The start number must be an integer.',
            'start_number.min' => 'The start number must be at least 1.',
            'end_number.required' => 'The end number is required.',
            'end_number.integer' => 'The end number must be an integer.',
            'end_number.min' => 'The end number must be at least 1.',
            'end_number.gte' => 'The end number must be greater than or equal to the start number.',
            'type.required' => 'The port type is required.',
            'type.Illuminate\Validation\Rules\Enum' => 'The selected port type is invalid.',
            'subtype.required' => 'The port subtype is required.',
            'subtype.Illuminate\Validation\Rules\Enum' => 'The selected port subtype is invalid.',
            'direction.Illuminate\Validation\Rules\Enum' => 'The selected port direction is invalid.',
        ];
    }
}
