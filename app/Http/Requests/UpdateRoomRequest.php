<?php

namespace App\Http\Requests;

use App\Enums\RoomType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    /**
     * Roles that can update rooms.
     *
     * @var array<string>
     */
    private const AUTHORIZED_ROLES = [
        'Administrator',
        'IT Manager',
    ];

    /**
     * Determine if the user is authorized to make this request.
     * Only Administrators and IT Managers can update rooms.
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'square_footage' => ['nullable', 'numeric', 'min:0'],
            'type' => ['required', Rule::enum(RoomType::class)],
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
            'name.required' => 'The room name is required.',
            'name.string' => 'The room name must be a string.',
            'name.max' => 'The room name must not exceed 255 characters.',
            'description.string' => 'The description must be a string.',
            'square_footage.numeric' => 'The square footage must be a number.',
            'square_footage.min' => 'The square footage must be at least 0.',
            'type.required' => 'The room type is required.',
            'type.Illuminate\Validation\Rules\Enum' => 'The selected room type is invalid.',
        ];
    }
}
