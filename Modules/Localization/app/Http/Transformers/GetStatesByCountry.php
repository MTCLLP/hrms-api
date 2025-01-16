<?php

namespace Modules\Localization\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class GetStatesByCountry extends JsonResource
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
        return $this->states;
    }
}
