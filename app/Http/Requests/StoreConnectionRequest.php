<?php

namespace App\Http\Requests;

use App\Enums\CableType;
use App\Enums\PortDirection;
use App\Enums\PortType;
use App\Models\Connection;
use App\Models\Port;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConnectionRequest extends FormRequest
{
    /**
     * Roles that can manage connections.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

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
            'source_port_id' => ['required', 'integer', 'exists:ports,id'],
            'destination_port_id' => ['required', 'integer', 'exists:ports,id', 'different:source_port_id'],
            'cable_type' => ['required', Rule::enum(CableType::class)],
            'cable_length' => ['required', 'numeric', 'min:0.01', 'max:9999.99'],
            'cable_color' => ['nullable', 'string', 'max:50'],
            'path_notes' => ['nullable', 'string', 'max:65535'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validatePortCompatibility($validator);
            $this->validatePowerDirectionality($validator);
            $this->validatePortsNotAlreadyConnected($validator);
        });
    }

    /**
     * Validate that source and destination ports are of compatible types.
     * Ethernet can only connect to Ethernet, Fiber can only connect to Fiber.
     */
    private function validatePortCompatibility(\Illuminate\Validation\Validator $validator): void
    {
        $sourcePort = Port::find($this->input('source_port_id'));
        $destPort = Port::find($this->input('destination_port_id'));

        if (! $sourcePort || ! $destPort) {
            return;
        }

        if ($sourcePort->type !== $destPort->type) {
            $validator->errors()->add(
                'destination_port_id',
                "The destination port must be the same type as the source port ({$sourcePort->type->label()})."
            );
        }
    }

    /**
     * Validate power connection directionality.
     * For power connections: source must be Output, destination must be Input.
     */
    private function validatePowerDirectionality(\Illuminate\Validation\Validator $validator): void
    {
        $sourcePort = Port::find($this->input('source_port_id'));
        $destPort = Port::find($this->input('destination_port_id'));

        if (! $sourcePort || ! $destPort) {
            return;
        }

        // Only validate power ports
        if ($sourcePort->type !== PortType::Power) {
            return;
        }

        // Source must be Output for power connections
        if ($sourcePort->direction !== PortDirection::Output) {
            $validator->errors()->add(
                'source_port_id',
                'For power connections, the source port must have Output direction.'
            );
        }

        // Destination must be Input for power connections
        if ($destPort->direction !== PortDirection::Input) {
            $validator->errors()->add(
                'destination_port_id',
                'For power connections, the destination port must have Input direction.'
            );
        }
    }

    /**
     * Validate that neither port already has an active connection.
     * Each port can only have one active connection.
     */
    private function validatePortsNotAlreadyConnected(\Illuminate\Validation\Validator $validator): void
    {
        $sourcePortId = $this->input('source_port_id');
        $destPortId = $this->input('destination_port_id');

        // Check if source port already has a connection
        $sourceHasConnection = Connection::where('source_port_id', $sourcePortId)
            ->orWhere('destination_port_id', $sourcePortId)
            ->exists();

        if ($sourceHasConnection) {
            $validator->errors()->add(
                'source_port_id',
                'The source port already has an active connection.'
            );
        }

        // Check if destination port already has a connection
        $destHasConnection = Connection::where('source_port_id', $destPortId)
            ->orWhere('destination_port_id', $destPortId)
            ->exists();

        if ($destHasConnection) {
            $validator->errors()->add(
                'destination_port_id',
                'The destination port already has an active connection.'
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
            'source_port_id.required' => 'The source port is required.',
            'source_port_id.exists' => 'The selected source port does not exist.',
            'destination_port_id.required' => 'The destination port is required.',
            'destination_port_id.exists' => 'The selected destination port does not exist.',
            'destination_port_id.different' => 'The destination port must be different from the source port.',
            'cable_type.required' => 'The cable type is required.',
            'cable_type.Illuminate\Validation\Rules\Enum' => 'The selected cable type is invalid.',
            'cable_length.required' => 'The cable length is required.',
            'cable_length.numeric' => 'The cable length must be a number.',
            'cable_length.min' => 'The cable length must be at least 0.01 meters.',
            'cable_length.max' => 'The cable length must not exceed 9999.99 meters.',
            'cable_color.string' => 'The cable color must be a string.',
            'cable_color.max' => 'The cable color must not exceed 50 characters.',
            'path_notes.string' => 'The path notes must be a string.',
            'path_notes.max' => 'The path notes are too long.',
        ];
    }
}
