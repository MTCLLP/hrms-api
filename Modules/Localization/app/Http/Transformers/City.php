<?php

namespace Modules\Localization\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

use Carbon\Carbon;

class City extends JsonResource
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
            'is_active' => $this->is_active,
            'state' => $this->state->name,
            'country' => $this->country->name,
            'created_by' => $this->createdBy,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),

        ];
    }
}
