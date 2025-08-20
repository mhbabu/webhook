<?php

namespace App\Http\Requests\Message;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreUserQuickReplyRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $userId = request()->user() ? request()->user()->id : null;
        return [
            'title'   => ['required', 'string', 'max:255', 'unique:user_quick_replies,title,NULL,id,user_id,' . $userId],
            'content' => ['required', 'string'],
            'status'  => ['required', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'   => 'The title field is required.',
            'title.unique'     => 'The title has already been taken for this user.',
            'content.required' => 'The content field is required.',
            'status.required'  => 'The status field is required.',
            'status.in'        => 'The status must be 0 or 1.',
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
            'status'  => false,
            'message' => $errors->first(),
            'errors'  => $errors
        ], 422);

        throw new HttpResponseException($response);
    }
}
