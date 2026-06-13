<?php

namespace Modules\Auth\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => 'nullable|string',
            'password' => 'required|string',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     * If 'email' field contains a non-email value, treat it as username.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->input('email');
        if ($input && !filter_var($input, FILTER_VALIDATE_EMAIL)) {
            // It's a username, not an email — pass it as 'name' for the controller
            $this->merge([
                'login_field' => 'name',
                'login_value' => $input,
            ]);
        } elseif ($input) {
            $this->merge([
                'login_field' => 'email',
                'login_value' => $input,
            ]);
        }
    }
}
