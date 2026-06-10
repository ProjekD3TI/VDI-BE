<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        return Http::timeout(60)->withHeaders([
            'Authorization' => "PVEAPIToken={$this->tokenId}={$this->tokenSecret}",
            'Accept' => 'application/json',
            'Connection' => 'close'
        ])->withoutVerifying();
    }
    public function getVms()
    {
        $response = $this->client()->get("{$this->baseURL}/nodes/{$this->node}/qemu");
        if ($response->failed()) {
            $this->handleError($response);
        }
        return collect($response->json('data'))
            ->filter(fn($vm) => !isset($vm['template']) || $vm['template'] != 1)
            ->values();
        ;
    }
    public function getDetailVms(int $vmid)
    {
        $response = $this->client()->get("{$this->baseURL}/nodes/{$this->node}/qemu/{$vmid}/status/current");
        if ($response->failed()) {
            $this->handleError($response);
        }
        return $response->json('data');
    }
    public function getFsInfo(int $vmid)
    {
        $response = $this->client()->get(
            "{$this->baseURL}/nodes/{$this->node}/qemu/{$vmid}/agent/get-fsinfo"
        );

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json('data.result');
    }
    public function getTemplate()
    {
        $response = $this->client()->get("{$this->baseURL}/nodes/{$this->node}/qemu");
        if ($response->failed()) {
            $this->handleError($response);
        }
        return collect($response->json('data'))->where('template', 1)->values()->all();
    }

    public function cloneVm($data)
    {
        $templateId = $data['template_id'];

        $response = $this->client()->post(
            "{$this->baseURL}/nodes/{$this->node}/qemu/{$templateId}/clone",
            [
                'newid' => $data['vmid'],
                'name' => $data['username'] ?? 'vm-' . $data['vmid'],
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
        $retries = 0;
        $maxRetries = 5;
        while (true) {
            try {
                $task = $this->checkTaskStatus($upid);

                Log::info('Task status', $task);

                if ($task['status'] === 'stopped') {
                    if ($task['exitstatus'] !== 'OK') {
                        throw new Exception("Task failed: " . $task['exitstatus']);
                    }
                    return true;
                }
            } catch (Throwable $e) {
                if (str_contains($e->getMessage(), 'cURL') || str_contains($e->getMessage(), 'Connection')) {
                    $retries++;
                    Log::warning("Proxmox API Network Error, retrying ($retries/$maxRetries)...", ['error' => $e->getMessage()]);

                    if ($retries >= $maxRetries) {
                        throw $e; // Lempar error jika gagal berulang kali
                    }
                    sleep(5); // Jeda sebelum mencoba kembali
                    continue;
                }

                // Jika error selain masalah koneksi, langsung lemparkan
                throw $e;
            }


            if (time() - $start > $timeout) {
                throw new Exception("Task timeout");
            }

            sleep(3);
        }
    }
    public function unlockVm($vmid)
    {
        // Menggunakan param 'delete' => 'lock' untuk menghapus status lock pada VM
        $response = $this->client()->asForm()->post(
            "{$this->baseURL}/nodes/{$this->node}/qemu/{$vmid}/config",
            ['delete' => 'lock']
        );

        if ($response->failed()) {
            // Kita log errornya saja, jangan jadikan exception agar proses rollback selanjutnya tidak terhenti
            Log::error("Failed to unlock VM {$vmid}", ['response' => $response->body()]);
        }

        return $response->json();
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
        // PERBAIKAN: Tambahkan asForm() agar tidak dikirim sebagai JSON Array kosong
        $response = $this->client()->asForm()->post(
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
    public function stopVM($vmid)
    {
        // Tambahkan juga asForm() di sini
        $response = $this->client()->asForm()->post(
            "{$this->baseURL}/nodes/{$this->node}/qemu/{$vmid}/status/stop"
        );

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json();
    }
    public function deleteVm($vmid)
    {
        $response = $this->client()->delete("{$this->baseURL}/nodes/$this->node/qemu/{$vmid}");

        if ($response->failed()) {
            $this->handleError($response);
        }

        return $response->json('data');
    }
    private function handleError(Response $response)
    {
        $message = null;

        $json = $response->json();

        if (is_array($json)) {
            $message = $json['message'] ?? null;

            if (!$message && isset($json['errors'])) {
                $message = is_array($json['errors'])
                    ? json_encode($json['errors'])
                    : $json['errors'];
            }
        }

        if (!$message) {
            $message = trim($response->body());
        }

        if (!$message) {
            $message = "Proxmox API Error ({$response->status()})";
        }
        Log::error($message);

        throw new Exception($message, $response->status());
    }
}