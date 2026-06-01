<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VMResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'vmid' => $this['vmid'],
            'name'=> $this['name'],
            'storage' => $this['maxdisk'] ?? 0,
            'ram' => $this['maxmem'] ?? 0,
            'status' => $this['status'] ?? null,
            'ip_address' => $this['local']?->ipAddress?->ip_address,
            'template_id' => $this['local']?->template_id,
        ];
    }
}
