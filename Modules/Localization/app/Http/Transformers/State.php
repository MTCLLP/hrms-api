<?php

namespace Modules\Localization\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

use Carbon\Carbon;

class State extends JsonResource
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
            'code' => $this->code,
            'iso_code' => $this->iso_code,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
            'deleted_at' => ($this->deleted_at ? Carbon::parse($this->deleted_at)->diffForHumans() : null),
            'country' => $this->country,
            'created_by' => $this->createdBy,
        ];
    }
}
