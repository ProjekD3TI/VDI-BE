<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Angkatan;
use Exception;
use Illuminate\Http\Request;

class AngkatanController extends Controller
{
    public function index()
    {
        try {
            $data = Angkatan::all();
            return response()->json([
                'message' => "Berhasil mendapatkan data Angkatan",
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'gagal mendapatkan data angkatan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function store(Request $req)
    {
        try {
            $req->validate([
                'angkatan' => 'required|integer'
            ]);
            $data = Angkatan::create([
                'angkatan' => $req->angkatan
            ]);
            return response()->json([
                'message' => 'berhasil menambahkan angkatan',
                'data' => $data
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'gagal menambahkan angkatan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function show($id)
    {
        try {
            $data = Angkatan::findOrFail($id);

            return response()->json([
                'message' => 'Detail Data',
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Data tidak ditemukan',
                'data' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {

            $request->validate([
                'angkatan' => 'required|integer'
            ]);

            $data = Angkatan::findOrFail($id);
            $data->update([
                'angkatan' => $request->angkatan
            ]);

            return response()->json([
                'message' => 'berhsil melakukan update angkatan',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'gagal update angkatan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $data = Angkatan::findOrFail($id);
            $data->delete();

            return response()->json([
                'message' => 'berhasil menghapus angkatan'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal Menghapus angkatan',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
