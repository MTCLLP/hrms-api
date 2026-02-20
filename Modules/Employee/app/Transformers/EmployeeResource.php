<?php

namespace Modules\Employee\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->user,
            'name' => $this->user->name,
            'department' => $this->department,
            'department_name' => $this->department->name,
            'jobTitle' => $this->jobTitle,
            'jobTitle_name' => $this->jobTitle->name,
            // Include full superior details
            'superiors' => $this->superiors->map(function ($superior) {
                $employee = $superior->superiorDetails;
                return $employee ? [
                    'id' => $employee->id,
                    'user' => [
                        'id' => $employee->user->id,
                        'name' => $employee->user->name,
                        'email' => $employee->user->email,
                    ],
                    'department' => $employee->department,
                    'department_name' => $employee->department->name,
                    'jobTitle' => $employee->jobTitle,
                    'jobTitle_name' => $employee->jobTitle->name,
                    'dob' => $employee->dob,
                    'dob_formatted' => ($employee->dob ? Carbon::parse($employee->dob)->format('d M Y') : null),
                    'gender' => $employee->gender,
                    'hire_date' => $employee->hire_date,
                    'hire_date_formatted' => ($employee->hire_date ? Carbon::parse($employee->hire_date)->format('d M Y') : null),
                    'profile_image' => $employee->profile_image,
                    'is_active' => $employee->is_active,
                    'is_trashed' => $employee->is_trashed,
                ] : null;
            }),

            // Include full subordinate details
            'subordinates' => $this->subordinates->map(function ($subordinate) {
                $employee = $subordinate->subordinateDetails;
                return $employee ? [
                    'id' => $employee->id,
                    'user' => [
                        'id' => $employee->user->id,
                        'name' => $employee->user->name,
                        'email' => $employee->user->email,
                    ],
                    'department' => $employee->department,
                    'department_name' => $employee->department->name,
                    'jobTitle' => $employee->jobTitle,
                    'jobTitle_name' => $employee->jobTitle->name,
                    'dob' => $employee->dob,
                    'dob_formatted' => ($employee->dob ? Carbon::parse($employee->dob)->format('d M Y') : null),
                    'gender' => $employee->gender,
                    'hire_date' => $employee->hire_date,
                    'hire_date_formatted' => ($employee->hire_date ? Carbon::parse($employee->hire_date)->format('d M Y') : null),
                    'profile_image' => $employee->profile_image,
                    'is_active' => $employee->is_active,
                    'is_trashed' => $employee->is_trashed,
                ] : null;
            }),

            'branch' => $this->branch,
            'dob' => $this->dob,
            'dob_formatted' => ($this->dob ? Carbon::parse($this->dob)->format('d M Y') : null),
            'gender' => $this->gender,
            'hire_date' => $this->hire_date,
            'hire_date_formatted' => ($this->hire_date ? Carbon::parse($this->hire_date)->format('d M Y') : null),
            'termination_date' => $this->termination_date,
            'profile_image' => $this->profile_image,
            'emails' => $this->emails,
            'contacts' => $this->contacts,
            'addresses' => $this->addresses,
            'experience' => $this->experiences,
            'dependants' => $this->dependants->where('is_trashed', false),
            'created_by' => $this->createdBy,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
        ];
    }
}
