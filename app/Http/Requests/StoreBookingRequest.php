<?php

namespace App\Http\Requests;

// use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // temp allows
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'space_id' => 'required|exists:spaces, id',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'notes' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.after' => 'Start time should be after now',
            'end_time.after' => 'End time should be after the start time',
        ];
    }
}
