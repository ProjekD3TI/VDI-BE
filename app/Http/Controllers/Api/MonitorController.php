<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VMs;

class MonitorController extends Controller
{
    public function getData()
    {
        try {
            $userCount = User::where('role', 'user')->count();
            $vms = VMs::count();

            return response()->json([
                'message' => 'Success get data.',
                'data' => [
                    'userCount' => $userCount,
                    'vms' => $vms,
                    'node' => env('PROXMOX_NODE'),
                    'ip_address' => env('PROXMOX_HOST')
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Failed to get data',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}
