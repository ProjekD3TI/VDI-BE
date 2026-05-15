<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateVmJob;
use App\Jobs\DeleteVmJob;
use App\Models\User;
use App\Models\VMs;
use App\Services\ProxmoxServices;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
            return response()->json([
                'message' => 'success to get vm',
                $this->proxmox->getVms()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'failed to get vm',
                'error' => $e
            ]);
        }
    }

    public function store(Request $request)
    {

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'template_id' => 'required',
            'ip_address' => 'required|ip',
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
        $vmData = [
            "user_id" => $user->id,
            "template_id" => $request->template_id,
            "vmid" => $vmid,
            "name" => $user->name,
            "username" => $user->username,
            "password" => $passwordPlain,
            "ip_address" => $request->ip_address,
            "email" => $user->email,
            "role" => $user->role ?? 'user'
        ];
        dispatch(new CreateVmJob($vmData));

        return response()->json([
            'message' => 'VM creation queued',
            'data' => $vmData
        ]);
    }

    public function destroy($id)
    {
        try {
            $vm = VMs::findOrFail($id);

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
}
