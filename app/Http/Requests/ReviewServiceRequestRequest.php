<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review service requests') ?? false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'revision', 'reject'])],
            'note' => ['nullable', 'required_unless:action,approve', 'string', 'max:5000'],
        ];
    }
}
