<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserByIdResource;
use App\Http\Resources\UserResource;
use App\Models\Angkatan;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use PHPUnit\Metadata\RequiresPhpExtension;
use Throwable;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::with(['vms', 'angkatan'])->where('role', 'user')->get();
            return response()->json([
                'message' => 'berhasil mengambil list user',
                'data' => UserResource::collection($users)
            ], 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'gagal mengambil data user',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    public function store(Request $request)
    {
        $request->validate([
            'nim' => [
                'required',
                'string',
                'unique:users,nim',
                'regex:/^V34\d{5}$/'
            ],
            'name' => [
                'required',
                'string',
                'min:3'
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'unique:users,username',
                'regex:/^[a-zA-Z0-9.-]+$/', // Hanya alfanumerik, titik, dan tanda hubung
                'regex:/^[a-zA-Z0-9]/',     // Harus diawali huruf atau angka
            ],
            'email' => [
                'required',
                'email',
                'unique:users,email',
                'regex:/@student\.uns\.ac\.id$/' // Wajib berakhiran @student.uns.ac.id
            ],
            'angkatan_id' => [
                'required',
                'integer' // Sama seperti z.coerce.number
            ],
        ]);


        // simpan ke database lokal
        $user = User::create([
            'username' => $request->username,
            'nim' => $request->nim,
            'name' => $request->name,
            'angkatan_id' => $request->angkatan_id,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'User berhasil dibuat',
            'data' => $user,
        ]);
    }

    public function getUserById($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'message' => 'data berhasil di ambil',
                'data' => new UserByIdResource($user)
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'data tidak ditemukan',
                'erroe' => $e->getMessage()
            ], 404);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'Gagal mendapatkan user',
                'error' => $th->getMessage()
            ], 500);
        }
    }
    public function destroy($id)
    {
        if (auth()->id() == $id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.'
            ], 403);
        }
        try {
            $data = User::with('vms')->findOrFail($id);

            if ($data->vms) {
                return response()->json([
                    'message' => 'User tidak dapat dihapus karena masih memiliki VM.'
                ], 409);
            }

            $data->delete($id);

            return response()->json([
                'message' => 'Berhasil menghapus data'
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Data Tidak Ditemukan',
                'error' => $e->getMessage()
            ], 403);
        } catch (Exception $th) {
            return response()->json([
                'message' => 'Terjadi kesalahan pada server.',
                'error' => $th->getMessage()
            ], 500);
        }
    }
}