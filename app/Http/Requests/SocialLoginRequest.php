<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialLoginRequest extends FormRequest
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
            // Apple returns the user's name ONLY on the first authorization;
            // every later sign-in sends null. Requiring it here rejected every
            // returning Apple user ("the name field is required") before the
            // controller's lookup-by-provider-id could even run. Name is only
            // actually needed when CREATING a new user, where the repository
            // supplies a fallback.
            'name' => 'nullable',
            'id' => 'required',
            'type' => 'required',
            'email' => 'nullable|email',
        ];
    }
}
