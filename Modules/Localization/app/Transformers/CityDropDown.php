<?php

namespace Modules\System\Transformers\Localization;

use Illuminate\Http\Resources\Json\JsonResource;

use Carbon\Carbon;

class CityDropDown extends JsonResource
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
            
        ];
    }
}
