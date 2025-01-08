<?php

namespace Modules\Leave\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class LeaveRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->employee,
            'user' => $this->employee->user,
            'leavetype_id' => $this->leavetype_id,
            'leavetype' => $this->leaveType,
            'status' => $this->status,
            'comments' => $this->comments,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'supervised_by' => $this->supervisedBy,
            'created_by' => $this->createdBy,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
        ];
    }
}
