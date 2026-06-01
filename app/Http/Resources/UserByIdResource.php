<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserByIdResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
           'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'nim' => $this->nim,
            'has_vm' => $this->vms !== null,
            'angkatan' => $this->angkatan?->angkatan ?? 'Tidak Diketahui',
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
