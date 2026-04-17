<?php


namespace App\Jobs;

use App\Services\GuacamoleService;
use App\Services\ProxmoxServices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateVmJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    protected $VmData;
    public $timeout = 300;
    public function __construct($VmData)
    {
        $this->VmData = $VmData;
    }

    public function handle(ProxmoxServices $proxmox, GuacamoleService $guacamole): void
    {
        try {
            Log::info('Start VM creation', $this->VmData);

            // 1. Clone VM
            $upid = $proxmox->cloneVm($this->VmData);
            Log::info('Clone started', ['upid' => $upid]);

            // 2. Wait clone selesai
            $proxmox->waitForTask($upid);
            Log::info('Clone finished');

            // 3. Config VM (cloud-init)
            $proxmox->configVm($this->VmData);
            Log::info('VM configured');

            // 4. Create User di Apache Guacamole
            $guacamoleUser = $guacamole->createUser([
                'username' => $this->VmData['username'],
                'password' => $this->VmData['password'], // Gunakan plaintext
                'name' => $this->VmData['name'],
                'email' => $this->VmData['email'],
                'role' => $this->VmData['role']
            ]);
            Log::info('User successfully created in Guacamole', ['guac_response' => $guacamoleUser]);
        } catch (\Throwable $th) {
            Log::error('VM creation failed', [
                'error' => $th->getMessage(),
                'data' => $this->VmData
            ]);

            throw $th; // biar bisa retry kalau queue gagal
        }
    }
}