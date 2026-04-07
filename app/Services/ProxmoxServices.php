<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
class ProxmoxServices
{
    protected $baseURL;
    protected $tokenId;
    protected $tokenSecret;


    public function __construct()
    {
        $this->baseURL = "https://" . env('PROXMOX_HOST') . ":8006/api2/json";
        $this->tokenId = env('PROXMOX_USER') . "!" . env('PROXMOX_TOKEN_ID');
        $this->tokenSecret = env('PROXMOX_TOKEN_SECRET');
    }

    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => "PVEAPIToken={$this->tokenId}={$this->tokenSecret}"
        ])->withoutVerifying();
    }
    public function getVms()
    {
        $node = env('PROXMOX_NODE');

        $response = $this->client()->get("{$this->baseURL}/nodes/{$node}/qemu");

        return $response->json('data'); 
    }
}