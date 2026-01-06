<?php

namespace Modules\Leave\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

use Modules\Leave\Transformers\LeaveBalanceResource;
use Modules\Leave\Transformers\LeaveApprovalResource;

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
            'leaveBalance' => LeaveBalanceResource::collection($this->leaveBalance),
            'leaveApproval' => LeaveApprovalResource::collection($this->leaveApprovals->sortByDesc('created_at')),
            'status' => $this->status,
            'is_half_day' => $this->is_half_day ? 'Yes' : 'No',
            'leave_description' => $this->leave_description,
            'comments' => $this->comments,
            'reason' => $this->reason,
            'start_date' => $this->start_date,
            'start_date_formatted' => ($this->start_date ? Carbon::parse($this->start_date)->format('d M Y') : null),
            'end_date' => $this->end_date,
            'end_date_formatted' => ($this->end_date ? Carbon::parse($this->end_date)->format('d M Y') : null),
            'supervised_by' => $this->supervisedBy,
            'created_by' => $this->createdBy,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'created_at_raw' => $this->created_at,
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
        ];
    }
}
