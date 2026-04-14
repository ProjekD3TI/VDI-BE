<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateVmJob;
use App\Services\ProxmoxServices;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        CreateVmJob::dispatch($request->all());

        return response()->json([
            'message' => 'VM creation queued'
        ]);
    }
}
