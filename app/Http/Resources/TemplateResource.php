<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'template_id'=>$this['vmid'],
            'name'=>$this['name'],
            'storage'=>$this['maxdisk'],
            'ram'=>$this['maxmem'],
            'cpu'=>$this['cpus']
        ];
    }
}
