<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ProxmoxException;
use App\Http\Controllers\Controller;
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
use Illuminate\Support\Facades\Hash;
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
            $proxmoxVms = $this->proxmox->getVms();

            $localVms = Vms::with('ipAddress')
                ->get()
                ->keyBy('vmid');

            return response()->json([
                'message' => 'success to get vm',
                'data' => VMResource::collection(
                    $proxmoxVms->map(function ($vm) use ($localVms) {
                        $vm['local'] = $localVms[$vm['vmid']] ?? null;
                        return $vm;
                    })
                )
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'failed to get vm',
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

        $passwordPlain = Str::random(10);
        $user->update([
            'password' => Hash::make($passwordPlain)
        ]);

        $vmid = (int) $matches[0];

        $ipModel = IpAddress::where('ip_address', $request->ip_address)->first();
        $ipModel->update(['status' => 'used']);

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

        dispatch(new CreateVmJob($vmData));

        return response()->json([
            'message' => 'VM creation queued',
            'data' => $vmData
        ]);
    }

    public function destroy($vmid)
    {
        try {
            $vm = VMs::where('vmid', $vmid)->first();

            if (!$vm) {
                return response()->json([
                    'message' => 'Data Vm dengan VMID {$vmid} tidak ditemukan di database lokal'
                ], 404);
            }

            dispatch(new DeleteVmJob($vm->id));
            return response()->json([
                'message' => 'Proses penghapusan VM telah di masukkan ke Antrean.',
                'data' => $vm->vmid,
                'ip_address' => $vm->ip_address
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal memicu penghapusan VM',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function startVm(Request $request)
    {
        $request->validate([
            'vmid' => 'required|integer|min:1'
        ]);

        try {
            // PERBAIKAN: Gunakan $request->vmid sesuai dengan yang divalidasi
            $result = $this->proxmox->startVm($request->vmid);

            return response()->json([
                'message' => "VM {$request->vmid} berhasil dijalankan",
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            // PERBAIKAN: Tangkap Exception umum yang dilempar oleh ProxmoxServices
            return response()->json([
                'message' => "Gagal menjalankan VM {$request->vmid}",
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function stopVm(Request $request)
    {
        $request->validate([
            'vmid' => 'required|integer|min:1'
        ]);
        try {
            // Memanggil method stopVM dari ProxmoxServices
            $result = $this->proxmox->stopVM($request->vmid);

            return response()->json([
                'message' => "VM {$request->vmid} berhasil dimatikan",
                'data' => $result
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'message' => "Gagal mematikan VM {$request->vmid}",
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getTemplate(){
         try {
           $templates = $this->proxmox->getTemplate();
           return TemplateResource::collection($templates);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil Data Template',
                'error' => $e->getMessage()
            ],$e->getCode());
        }
    }
}
