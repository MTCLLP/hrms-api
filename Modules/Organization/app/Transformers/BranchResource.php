<?php

namespace Modules\Organization\Transformers\Organization;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Modules\Localization\Transformers\CityDropDown as CityDropDownResource;
use Modules\Localization\Transformers\StateDropDown as StateDropDownResource;
use Modules\Localization\Transformers\CountryDropDown as CountryDropDownResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        // return parent::toArray($request);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'contact_no' => $this->contact_no,
            'city' => new CityDropDownResource($this->city),
            'state' => new StateDropDownResource($this->state),
            'country' => new CountryDropDownResource($this->country),
            'created_by' => $this->createdBy,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
        ];
    }
}
