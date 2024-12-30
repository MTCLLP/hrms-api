<?php

namespace Modules\RBAC\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

use Carbon\Carbon;

class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        //return parent::toArray($request);

        return [
            'profile_id' => $this->profile_id,
            'specialization' => $this->specialization,
            'qualification' => $this->qualification,
            'number' => $this->number,
            'clinic_name' => $this->clinic_name,
            'address' => $this->address,
            'alt_address' => $this->alt_address,
            'user_id' => $this->user_id,
            'created_at'=>($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
        ];
    }
}
