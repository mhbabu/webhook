<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSubCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'conversation_category_id' => 'sometimes|required|exists:conversation_categories,id',
            'name' => 'sometimes|required|string|max:255',
        ];
    }

    /**
     * Get the custom error messages for the validator.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'conversation_category_id.required' => 'The category ID is required.',
            'conversation_category_id.exists' => 'The selected category does not exist.',
            'name.required' => 'The subcategory name is required.',
            'name.string' => 'The subcategory name must be a string.',
            'name.max' => 'The subcategory name must not exceed 255 characters.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        $errors = $validator->errors();
        $response = response()->json([
            'success' => false,
            'message' => $errors->first(),
            'errors' => $errors
        ], 422);

        throw new HttpResponseException($response);
    }
}
