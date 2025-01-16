<?php

namespace Modules\RBAC\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

use Carbon\Carbon;
use Modules\Employee\Transformers\EmployeeResource;

class User extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'roles' => $this->roles,
            'permissions' => $this->permissions,
            'employee' => $this->employee ? new EmployeeResource($this->employee) : null,
            'created_at'=>($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
        ];
    }
}
