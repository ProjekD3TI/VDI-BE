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
            'name' => $this->user?->username,
            'user' => $this->user?->name,
            'ip_address' => $this->ipAddress?->ip_address,
            'status' => $this['status'],
        ];
    }
}
