<?php

namespace App\Http\Requests;

// use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // temp allow
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:desk,office,meeting_room',
            'capacity' => 'required|integer|min:1',
            'price_per_hour' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'features' => 'nullable|array',
            'image' => 'nullable|image|max:5120',
        ];
    }
}
