<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProxmoxException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DetailVmResource;
use App\Http\Resources\TemplateResource;
use App\Http\Resources\VMResource;
use App\Jobs\CreateVmJob;
use App\Jobs\DeleteVmJob;
use App\Models\IpAddress;
use App\Models\User;
use App\Models\VMs;
use App\Services\ProxmoxServices;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class VmController extends Controller
{
    protected $proxmox;
    public function __construct(ProxmoxServices $proxmox)
    {
        $this->proxmox = $proxmox;
    }

    public function index()
    {
        try {
            $localVms = Vms::with('ipAddress', 'user')
                ->get()
                ->keyBy('vmid');

            return response()->json([
                'message' => 'success to get vm',
                'data' => VMResource::collection($localVms)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'failed to get vm',
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }
    public function getDetailVm(int $vmid)
    {
        try {
            $proxmoxVm = $this->proxmox->getDetailVms($vmid);

            $proxmoxVm['local'] = VMs::with('ipAddress', 'user')
                ->where('vmid', $vmid)
                ->first();
            $proxmoxVm['storage_used'] = null;
            $proxmoxVm['storage_total_guest'] = null;
            $proxmoxVm['storage_available'] = null;
            $proxmoxVm['storage_usage_percent'] = null;
            if (($proxmoxVm['status'] ?? null) === 'running') {
                try {
                    $fsInfo = $this->proxmox->getFsInfo($vmid);

                    $rootFs = collect($fsInfo)
                        ->firstWhere('mountpoint', '/');

                    if ($rootFs) {
                        $total = round(($rootFs['total-bytes'] ?? 0) / 1024 / 1024 / 1024, 2);
                        $used = round(($rootFs['used-bytes'] ?? 0) / 1024 / 1024 / 1024, 2);

                        $proxmoxVm['storage_used'] = $used;
                        $proxmoxVm['storage_total_guest'] = $total;
                        $proxmoxVm['storage_available'] = round($total - $used, 2);
                        $proxmoxVm['storage_usage_percent'] = $total > 0
                            ? round(($used / $total) * 100, 2)
                            : 0;
                    }
                } catch (Exception $e) {
                    Log::warning("Failed to get fsinfo for VM {$vmid}: {$e->getMessage()}");
                }
            }

            return response()->json([
                'message' => "Success to get VM {$vmid}.",
                'data' => new DetailVmResource($proxmoxVm)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to get VM.',
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'template_id' => 'required',
            'ip_address' => [
                'required',
                'ip',
                Rule::exists('ip_addresses', 'ip_address')->where(function ($query) {
                    return $query->where('status', 'free');
                }),
            ],
        ], [
            'ip_address.exists' => 'IP Address tidak valid atau sudah digunakan oleh VM lain.'
        ]);

        $user = User::findOrFail($request->user_id);
        $nim = $user->nim;

        if (!preg_match('/\d{5}$/', $nim, $matches)) {
            return response()->json([
                'message' => 'Format NIM Tidak Valid'
            ], 400);
        }

        $vmid = (int) $matches[0];
        $passwordPlain = Str::random(10);

        // Menggunakan DB Transaction untuk mencegah data tidak sinkron jika terjadi error
        DB::beginTransaction();
        try {
            // 1. Update Password User
            $user->update([
                'password' => Hash::make($passwordPlain)
            ]);

            // 2. Update Status IP Address
            $ipModel = IpAddress::where('ip_address', $request->ip_address)->first();
            $ipModel->update(['status' => 'used']);

            // 3. LOCK SISTEM: Insert ke tabel VMs dengan status 'creating'
            $vm = VMs::create([
                'user_id' => $user->id,
                'vmid' => $vmid,
                'ip_address_id' => $ipModel->id,
                'template_id' => $request->template_id,
                'status' => 'creating' // Pastikan migration sudah di-update dengan enum ini
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal menyiapkan data VM di database.',
                'error' => $e->getMessage()
            ], $e->getCode());
        }

        // 4. Siapkan Data untuk Job
        $vmData = [
            "user_id" => $user->id,
            "template_id" => $request->template_id,
            "vmid" => $vmid,
            "name" => str_replace(' ', '-', $user->name),
            "username" => $user->username,
            "password" => $passwordPlain,
            "ip_address" => $ipModel->ip_address,
            "ip_address_id" => $ipModel->id,
            "email" => $user->email,
            "role" => $user->role ?? 'user'
        ];

        // 5. Lempar ke Queue
        dispatch(new CreateVmJob($vmData))->onQueue('vm-provisioning');

        return response()->json([
            'message' => 'VM creation queued',
            'data' => $vmData
        ]);
    }

    public function destroy($vmid)
    {
        try {
            $vm = VMs::where('vmid', $vmid)->first();
            if ($vm->status === 'creating') {
                return response()->json([
                    'message' => "VM {$vmid} is still in the process of being created and cannot be deleted yet."
                ], 400);
            }
            if (!$vm) {
                return response()->json([
                    'message' => "Vm data with VMID {$vmid} was not found in the local database."
                ], 404);
            }

            dispatch(new DeleteVmJob($vm->id))->onQueue('vm-provisioning');
            return response()->json([
                'message' => 'The VM deletion process has been entered into the Queue.',
                'data' => $vm->vmid,
                'ip_address' => $vm->ip_address
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete VM.',
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function startVm(Request $request)
    {
        $request->validate([
            'vmid' => 'required|integer|min:1'
        ]);

        try {

            // Cari VM di database
            $vm = VMs::where('vmid', $request->vmid)->first();

            if (!$vm) {
                return response()->json([
                    'message' => "VM {$request->vmid} tidak ditemukan"
                ], 404);
            }
            if ($vm->status === 'running') {
                return response()->json([
                    'message' => "VM {$request->vmid} sudah dalam keadaan running"
                ], 400);
            }

            // Cek status VM
            if ($vm->status === 'creating') {
                return response()->json([
                    'message' => "VM {$request->vmid} masih dalam proses pembuatan dan belum dapat dijalankan"
                ], 400);
            }

            // Jalankan VM di Proxmox
            $result = $this->proxmox->startVm($request->vmid);

            // Update status jika berhasil
            $vm->status = 'running';
            $vm->save();

            return response()->json([
                'message' => "Success to start VM {$request->vmid} ",
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => "Gagal menjalankan VM {$request->vmid}",
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }

    public function stopVm(Request $request)
    {
        $request->validate([
            'vmid' => 'required|integer|min:1'
        ]);

        try {

            // Cari VM
            $vm = VMs::where('vmid', $request->vmid)->first();

            if (!$vm) {
                return response()->json([
                    'message' => "VM {$request->vmid} tidak ditemukan"
                ], 404);
            }

            // VM masih dibuat
            if ($vm->status === 'creating') {
                return response()->json([
                    'message' => "VM {$request->vmid} masih dalam proses pembuatan dan tidak dapat dimatikan"
                ], 400);
            }

            // VM sudah mati
            if ($vm->status === 'stopped') {
                return response()->json([
                    'message' => "VM {$request->vmid} sudah dalam keadaan stopped"
                ], 400);
            }

            // Stop VM di Proxmox
            $result = $this->proxmox->stopVM($request->vmid);

            // Update status database
            $vm->status = 'stopped';
            $vm->save();

            return response()->json([
                'message' => "Success to stop {$request->vmid}",
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => "Gagal mematikan VM {$request->vmid}",
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getTemplate()
    {
        try {
            $templates = $this->proxmox->getTemplate();
            return TemplateResource::collection($templates);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil Data Template',
                'error' => $e->getMessage()
            ], $e->getCode());
        }
    }
}
