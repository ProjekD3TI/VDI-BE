<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Angkatan;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class AngkatanController extends Controller
{
    public function index()
    {
        try {
            $data = Angkatan::orderByDesc('angkatan')->get();
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
                'angkatan' => 'required|integer|unique:angkatans,angkatan'
            ]);
            $data = Angkatan::create([
                'angkatan' => $req->angkatan
            ]);
            return response()->json([
                'message' => 'Data successfully added.',
                'data' => $data
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'error' => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Internal server error.',
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
                'angkatan' => 'required|integer|unique:angkatans,angkatan'
            ]);

            $data = Angkatan::findOrFail($id);
            $data->update([
                'angkatan' => $request->angkatan
            ]);

            return response()->json([
                'message' => 'Success Update data.',
                'data' => $data
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'error' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Internal Server Error.',
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
                'message' => 'Success deleting data.'
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Data not found.',
                'error' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed deleting data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
