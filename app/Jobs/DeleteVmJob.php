<?php

namespace App\Jobs;

use App\Models\VMs;
use App\Services\GuacamoleService;
use App\Services\ProxmoxServices;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeleteVmJob implements ShouldQueue
{
    use Queueable;
    protected $vmId; // Ini adalah primary key (ID) dari tabel vms
    public $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct($vmId)
    {
        $this->vmId = $vmId;
    }

    /**
     * Execute the job.
     */
    public function handle(ProxmoxServices $proxmox, GuacamoleService $guacamole): void
    {
        // 1. Ambil data VM beserta data User-nya
        $vm = VMs::with('user')->find($this->vmId);

        if (!$vm) {
            Log::warning("DeleteVmJob: Data VM dengan ID lokal {$this->vmId} tidak ditemukan.");
            return; // Jika di DB sudah tidak ada, batalkan proses
        }

        $username = $vm->user->username ?? null;

        Log::info("Memulai proses Delete VM untuk user: {$username}");

        // 2. Hapus VM di Proxmox
        if ($vm->vmid) {
            try {
                $proxmox->stopVm($vm->vmid);
                sleep(3); // Jeda sejenak agar Proxmox selesai mematikan VM

                $upid = $proxmox->deleteVm($vm->vmid);
                $proxmox->waitForTask($upid);
                Log::info("Proxmox: VM {$vm->vmid} berhasil dihapus.");
            } catch (\Throwable $th) {
                Log::error("Proxmox: Gagal menghapus VM {$vm->vmid}", ['error' => $th->getMessage()]);
                // Kita tidak throw error di sini agar step selanjutnya tetap berjalan
            }
        }

        // 3. Hapus Connection di Guacamole
        if ($vm->guac_connection_id) {
            try {
                $guacamole->deleteConnection($vm->guac_connection_id);
                Log::info("Guacamole: Connection {$vm->guac_connection_id} berhasil dihapus.");
            } catch (\Throwable $th) {
                Log::error("Guacamole: Gagal menghapus koneksi", ['error' => $th->getMessage()]);
            }
        }

        // 4. Hapus User di Guacamole
        if ($username) {
            try {
                $guacamole->deleteUser($username);
                Log::info("Guacamole: User {$username} berhasil dihapus.");
            } catch (\Throwable $th) {
                Log::error("Guacamole: Gagal menghapus user", ['error' => $th->getMessage()]);
            }
        }

        // 5. Hapus Data dari Database Lokal
        try {
            $vm->delete();
            Log::info("Database: Record VM berhasil dihapus secara lokal.");
        } catch (\Throwable $th) {
            Log::error("Database: Gagal menghapus record VM", ['error' => $th->getMessage()]);
            throw $th; // Jika gagal hapus DB, Job harus ditandai failed agar bisa di-retry
        }
    }
}
