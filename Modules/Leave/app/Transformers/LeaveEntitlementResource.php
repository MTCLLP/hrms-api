<?php

namespace Modules\Leave\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class LeaveEntitlementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'leavetype_id' => $this->leavetype_id,
            'leave_type' => $this->leaveType,
            'leave_type_name' => $this->leaveType->type_name,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee,
            'employee_name' => $this->employee->user->name,
            'ent_amount' => $this->ent_amount,
            'ent_start_date' => ($this->ent_start_date ? Carbon::parse($this->ent_start_date)->format('d M Y') : null),
            'ent_end_date' => ($this->ent_end_date ? Carbon::parse($this->ent_end_date)->format('d M Y') : null),
            'created_by' => $this->createdBy,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
        ];
    }
}
