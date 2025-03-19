<?php

namespace Modules\Leave\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Carbon\Carbon;

class HolidayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => ($this->date ? Carbon::parse($this->date)->format('d M Y') : null),
            'description' => $this->description,
            'description_short' => Str::limit($this->description, 30),
            'created_by' => $this->createdBy,
            'is_active' => $this->is_active,
            'is_trashed' => $this->is_trashed,
            'created_at' => ($this->created_at ? Carbon::parse($this->created_at)->diffForHumans() : null),
            'updated_at' => ($this->updated_at ? Carbon::parse($this->updated_at)->diffForHumans() : null),
        ];
    }
}
