<?php

namespace App\Http\Requests;

use App\Enums\DeviceRackFace;
use App\Enums\DeviceWidthType;
use App\Models\Device;
use App\Models\EquipmentMove;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Form request for creating a new equipment move request.
 *
 * Validates device selection, destination location, and ensures
 * the device is not already in a pending move request.
 */
class StoreEquipmentMoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', EquipmentMove::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => [
                'required',
                'integer',
                'exists:devices,id',
                function ($attribute, $value, $fail) {
                    // Check if device already has a pending move
                    $hasPendingMove = EquipmentMove::where('device_id', $value)
                        ->where('status', 'pending_approval')
                        ->exists();

                    if ($hasPendingMove) {
                        $fail('This device already has a pending move request.');
                    }

                    // Check if device is placed in a rack
                    $device = Device::find($value);
                    if ($device && ! $device->rack_id) {
                        $fail('This device is not placed in a rack and cannot be moved.');
                    }
                },
            ],
            'destination_rack_id' => [
                'required',
                'integer',
                'exists:racks,id',
            ],
            'destination_start_u' => [
                'required',
                'integer',
                'min:1',
                'max:48',
            ],
            'destination_rack_face' => [
                'required',
                Rule::enum(DeviceRackFace::class),
            ],
            'destination_width_type' => [
                'required',
                Rule::enum(DeviceWidthType::class),
            ],
            'operator_notes' => [
                'nullable',
                'string',
                'max:65535',
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
            'device_id.required' => 'Please select a device to move.',
            'device_id.exists' => 'The selected device does not exist.',
            'destination_rack_id.required' => 'Please select a destination rack.',
            'destination_rack_id.exists' => 'The selected destination rack does not exist.',
            'destination_start_u.required' => 'Please specify the destination U position.',
            'destination_start_u.integer' => 'The U position must be a whole number.',
            'destination_start_u.min' => 'The U position must be at least 1.',
            'destination_start_u.max' => 'The U position cannot exceed 48.',
            'destination_rack_face.required' => 'Please select the destination rack face.',
            'destination_rack_face.Illuminate\Validation\Rules\Enum' => 'The selected rack face is invalid.',
            'destination_width_type.required' => 'Please select the destination width type.',
            'destination_width_type.Illuminate\Validation\Rules\Enum' => 'The selected width type is invalid.',
            'operator_notes.max' => 'The operator notes must not exceed 65535 characters.',
        ];
    }
}
