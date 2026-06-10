<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DetailVmResource extends JsonResource
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
            'name' => $this['name'],
            'status' => $this['status'],

            'cpus' => $this['cpus'],

            'cpu_usage' => round(($this['cpu'] ?? 0) * 100, 2),

            'ram_used' => round(($this['mem'] ?? 0) / 1024 / 1024 / 1024, 2),

            'ram_total' => round(($this['maxmem'] ?? 0) / 1024 / 1024 / 1024, 2),

            'ram_available' => ($this['maxmem'] ?? 0) > 0
                ? round((($this['maxmem'] ?? 0) - ($this['mem'] ?? 0)) / 1024 / 1024 / 1024, 2)
                : 0,

            'ram_usage_percent' => ($this['maxmem'] ?? 0) > 0
                ? round((($this['mem'] ?? 0) / $this['maxmem']) * 100, 2)
                : 0,

            // dari Proxmox status/current
            'storage_total_allocated' => round(($this['maxdisk'] ?? 0) / 1024 / 1024 / 1024, 2),

            // dari Guest Agent get-fsinfo
            'storage_used' => $this['storage_used'] ?? null,
            'storage_total' => $this['storage_total_guest'] ?? null,
            'storage_available' => $this['storage_available'] ?? null,
            'storage_usage_percent' => $this['storage_usage_percent'] ?? null,

            'uptime' => $this->formatUptime($this['uptime'] ?? 0),
            'ip_address' => $this['local']?->ipAddress?->ip_address,

            'template_id' => $this['local']?->template_id,
        ];
    }
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return "{$days}d:{$hours}h:{$minutes}m";
    }
}
