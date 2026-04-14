<?php


namespace App\Jobs;

use App\Services\ProxmoxServices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class CreateVmJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected $data;
    public $timeout = 300;
    public function __construct($data)
    {
        $this->data = $data;
    }

    public function handle(ProxmoxServices $proxmox): void
    {
        try {
            $upid = $proxmox->cloneVm($this->data);

            $proxmox->waitForTask($upid);

            sleep(5); // penting

            $proxmox->configVm($this->data);

        } catch (\Throwable $th) {
            Log::error('VM creation failed: ' . $th->getMessage());
            throw $th; // biar masuk failed_jobs
        }
    }
}