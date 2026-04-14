<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

class ProxmoxServices
{
    protected $baseURL;
    protected $tokenId;
    protected $tokenSecret;
    protected $node;


    public function __construct()
    {
        $this->baseURL = "https://" . env('PROXMOX_HOST') . ":8006/api2/json";
        $this->tokenId = env('PROXMOX_USER') . "!" . env('PROXMOX_TOKEN_ID');
        $this->tokenSecret = env('PROXMOX_TOKEN_SECRET');
        $this->node = env('PROXMOX_NODE');
    }

    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => "PVEAPIToken={$this->tokenId}={$this->tokenSecret}",
            'Accept' => 'application/json',
        ])->withoutVerifying();
    }
    public function getVms()
    {

        $response = $this->client()->get("{$this->baseURL}/nodes/{$this->node}/qemu");

        return $response->json('data');
    }

    public function cloneVm($data)
    {
        $templateId = $data['template_id'];

        $response = $this->client()->post(
            "{$this->baseURL}/nodes/{$this->node}/qemu/{$templateId}/clone",
            [
                'newid' => $data['vmid'],
                'name' => $data['name'] ?? 'vm-' . $data['vmid'],
                'full' => 1,
            ]
        );

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json('data'); // UPID
    }
    public function waitForTask($upid, $timeout = 180)
    {
        $start = time();

        while (true) {
            $task = $this->checkTaskStatus($upid);

            Log::info('Task status', $task);

            if ($task['status'] === 'stopped') {
                if ($task['exitstatus'] !== 'OK') {
                    throw new Exception("Task failed: " . $task['exitstatus']);
                }
                return true;
            }

            if (time() - $start > $timeout) {
                throw new Exception("Task timeout");
            }

            sleep(3);
        }
    }
    public function configVm($data)
    {
        $response = $this->client()->post(
            "{$this->baseURL}/nodes/{$this->node}/qemu/{$data['vmid']}/config",
            [
                'ciuser' => $data['username'],
                'cipassword' => $data['password'],
                'ipconfig0' => "ip={$data['ip_address']}/23,gw=" . env('PROXMOX_GATEWAY', '10.109.0.1'),
                'nameserver' => env('PROXMOX_DNS', '8.8.8.8')
            ]
        );

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json();
    }
    public function startVm($vmid)
    {
        $response = $this->client()->post(
            "{$this->baseURL}/nodes/{$this->node}/qemu/{$vmid}/status/start"
        );

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json();
    }

    public function checkTaskStatus($upid)
    {
        $node = $this->node;
        $response = $this->client()->get("{$this->baseURL}/nodes/{$node}/tasks/{$upid}/status");

        if ($response->failed()) {
            $this->handleError($response);
        }
        return $response->json('data');
    }

    private function handleError(Response $response)
    {
        $errorData = $response->json();
        $errorMessage = $errorData['errors'] ?? $errorData['message'] ?? 'Proxmox API Error';

        throw new Exception("Proxmox Error:" . json_encode($errorMessage));
    }
}