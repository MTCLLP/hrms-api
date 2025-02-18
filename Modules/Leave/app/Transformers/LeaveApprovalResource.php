<?php

namespace Modules\Leave\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

use Modules\Leave\Transformers\LeaveRequestResource;

class LeaveApprovalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'leaverequest_id' => $this->leaverequest_id,
            'status' => $this->status,
            'approver_id' => $this->approver_id,
            'approver_name' => $this->approver->user->name,
            'approver' => $this->approver,
            //'leaveRequest' => LeaveRequestResource::collection($this->leaverequest),
            'isPaidLeave' => $this->isPaidLeave ? 'Yes' : 'No',
            'remarks' => $this->remarks,
            'start_date' => $this->start_date,
            'start_date_formatted' => ($this->start_date ? Carbon::parse($this->start_date)->format('d M Y') : null),
            'end_date' => $this->end_date,
            'end_date_formatted' => ($this->end_date ? Carbon::parse($this->end_date)->format('d M Y') : null),
            'total_days' => $this->total_days,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
        ];
    }
}
