<?php

namespace Modules\Employee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeAddressRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'address' => 'required|string|max:255',
            'address_type' => 'required|string|max:255',
            'is_active' => 'required|boolean',
            'is_trashed' => 'required|boolean',
            'created_by' => 'nullable|exists:users,id',
            'deleted_at' => 'nullable|date',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
