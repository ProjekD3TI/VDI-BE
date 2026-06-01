<?php


namespace App\Jobs;

use App\Mail\VmCredentialMail;
use App\Models\IpAddress;
use App\Models\VMs;
use App\Services\GuacamoleService;
use App\Services\ProxmoxServices;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
     $proxmoxVmCreated = false;
        $guacUserCreated = false;
        $guacConnectionId = null;

        try {
            Log::info('Start VM creation', $this->VmData);

            // 1. Clone VM
            $upid = $proxmox->cloneVm($this->VmData);
            Log::info('Clone started', ['upid' => $upid]);

            // 2. Wait clone selesai
            $proxmox->waitForTask($upid);
            $proxmoxVmCreated = true; 
            Log::info('Clone finished');

            // 3. Config VM (cloud-init)
            $proxmox->configVm($this->VmData);
            Log::info('VM configured');

            // 4. Create User di Apache Guacamole
            $guacamoleUser = $guacamole->createUser([
                'username' => $this->VmData['username'],
                'password' => $this->VmData['password'], 
                'name' => $this->VmData['name'],
                'email' => $this->VmData['email'],
                'role' => $this->VmData['role']
            ]);
            $guacUserCreated = true; 
            Log::info('User successfully created in Guacamole', ['guac_response' => $guacamoleUser]);

            // 5. Create VNC Connection di Guacamole
            // Menggunakan string IP
            $connectionName = "VM - " . $this->VmData['username'];
            $connection = $guacamole->createConnection([
                'name' => $connectionName,
                'ip_address' => $this->VmData['ip_address'] 
            ]);
            $guacConnectionId = $connection['identifier']; 
            Log::info('Connection created in Guacamole', ['connection_id' => $guacConnectionId]);

            // 6. Assign User ke Connection
            $guacamole->assignUserToConnection(
                $this->VmData['username'],
                $guacConnectionId
            );
            Log::info('User successfully assigned to connection');

            // 7. Simpan ke Database Lokal
            // MENGGUNAKAN ip_address_id
            VMs::create([
                'user_id' => $this->VmData['user_id'],
                'vmid' => $this->VmData['vmid'],
                'ip_address_id' => $this->VmData['ip_address_id'], // Update disini
                'guac_connection_id' => $guacConnectionId,
                'template_id' => $this->VmData['template_id']
            ]);

            // 8. Kirim Email
            Mail::to($this->VmData['email'])->send(new VmCredentialMail($this->VmData));
            Log::info('Credential email send to ' . $this->VmData['email']);

        } catch (Throwable $th) {
            Log::error('VM creation failed, starting rollback...', [
                'error' => $th->getMessage(),
                'vmid' => $this->VmData['vmid']
            ]);

            // --- PROSES ROLLBACK ---
            
            // 0. Rollback Status IP Address kembali menjadi 'free'
            try {
                $ipModel = IpAddress::find($this->VmData['ip_address_id']);
                if ($ipModel) {
                    $ipModel->update(['status' => 'free']);
                    Log::info('Rollback: IP Address status reverted to free');
                }
            } catch (Throwable $ipEx) {
                Log::error('Rollback Failed: Reverting IP status', ['error' => $ipEx->getMessage()]);
            }

            // 1. Rollback Database Lokal (Jika sempat tersimpan sebelum email gagal)
            try {
                $localVm = VMs::where('vmid', $this->VmData['vmid'])->first();
                if ($localVm) {
                    $localVm->delete();
                    Log::info('Rollback: Local DB record deleted');
                }
            } catch (Throwable $dbEx) {
                Log::error('Rollback Failed: Local DB deletion', ['error' => $dbEx->getMessage()]);
            }

            // 2. Rollback Guacamole Connection
            if ($guacConnectionId) {
                try {
                    $guacamole->deleteConnection($guacConnectionId);
                    Log::info('Rollback: Guacamole connection deleted', ['connection_id' => $guacConnectionId]);
                } catch (Throwable $guacConnEx) {
                    Log::error('Rollback Failed: Guacamole connection', ['error' => $guacConnEx->getMessage()]);
                }
            }

            // 3. Rollback Guacamole User
            if ($guacUserCreated) {
                try {
                    $guacamole->deleteUser($this->VmData['username']);
                    Log::info('Rollback: Guacamole user deleted', ['username' => $this->VmData['username']]);
                } catch (Throwable $guacUserEx) {
                    Log::error('Rollback Failed: Guacamole user', ['error' => $guacUserEx->getMessage()]);
                }
            }

            // 4. Rollback Proxmox VM
            if ($proxmoxVmCreated) {
                try {
                    $proxmox->stopVM($this->VmData['vmid']);
                    Log::info('Rollback: Stop VM Command Sent');

                    sleep(3); // Beri jeda agar VM benar-benar mati sebelum didelete
                    
                    $deleteUpid = $proxmox->deleteVm($this->VmData['vmid']);
                    Log::info('Rollback: Start to Delete VM from Proxmox');

                    $proxmox->waitForTask($deleteUpid);
                    Log::info('Rollback: VM successfully deleted from Proxmox');
                } catch (Throwable $proxmoxEx) {
                    Log::critical('Critical: Rollback Failed for Proxmox VM', [
                        'vmid' => $this->VmData['vmid'],
                        'rollback_error' => $proxmoxEx->getMessage()
                    ]);
                }
            }

            throw $th;
        }
    }
}