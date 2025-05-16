<?php

namespace Modules\Employee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'hire_date' => 'required|date',
            'termination_date' => 'nullable|date',
            'dob' => 'required|date',
            'gender' => 'required|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'jobTitle_id' => 'nullable|exists:job_titles,id',
            'department_id' => 'nullable|exists:departments,id',
            'profile_image' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
            'is_trashed' => 'required|boolean',
            'deleted_at' => 'nullable|date',
            'created_by' => 'nullable|exists:users,id',
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
