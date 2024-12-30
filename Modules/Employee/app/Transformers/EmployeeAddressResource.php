<?php

namespace Modules\HR\Transformers\Organization;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class EmployeeAddressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return[
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'address' => $this->address,
            'address_type' => $this->address_type,
            'created_by' => $this->createdBy,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
        ];
    }
}
