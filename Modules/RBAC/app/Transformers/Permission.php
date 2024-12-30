<?php

namespace Modules\RBAC\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class Permission extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'guard_name' => $this->guard_name,
            'roles' => $this->roles,
            'created_at'=>($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
        ];
    }
}
