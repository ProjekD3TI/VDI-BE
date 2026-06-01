<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IpAddress;
use Illuminate\Http\Request;

class IpAddressController extends Controller
{
    public function index()
    {
        try {
            $ips = IpAddress::paginate(50); 
            return response()->json([
                'message' => 'Berhasil mengambil semua data IP',
                'data' => $ips
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message'=>'Gagagl Mengambil data',
                'error'=>$th->getMessage()
            ],500);
        }
        
    }

    
    public function available()
    {
        try {
            $availableIps = IpAddress::where('status', 'free')
            ->select('id', 'ip_address')
            ->get();
            
            return response()->json([
                'message' => 'Berhasil mengambil IP yang tersedia',
                'data' => $availableIps
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message'=>'Gagal Mengambil Data',
                'error'=>$th->getMessage()
            ]);
        }
    }
}
