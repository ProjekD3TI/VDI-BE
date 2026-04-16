<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GuacamoleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    protected $guacamoleService;

    public function __construct(GuacamoleService $guacamoleService)
    {
        $this->guacamoleService = $guacamoleService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'username' => 'required|string|unique:users,username',
            'name' => 'required|string',
            'nim' => 'required|string',
            'angkatan_id' => 'required',
            'role' => 'required|string',
            'email' => 'required|email|unique:users,email',
        ]);

        // generate password
        $plainPassword = Str::random(8);

        // simpan ke database lokal
        $user = User::create([
            'username' => $request->username,
            'nim' => $request->nim,
            'name' => $request->name,
            'angkatan_id' => $request->angkatan_id,
            'role' => $request->role,
            'email' => $request->email,
            'password' => Hash::make($plainPassword),
        ]);

        // kirim ke guacamole
        try {
            $this->guacamoleService->createUser([
                'username' => $request->username,
                'password' => $plainPassword,
                'email' => $request->email,
                'role' => $request->role,
                'name' => $request->name
            ]);
        } catch (Exception $e) {
            // rollback kalau gagal
            $user->delete();

            return response()->json([
                'message' => 'Gagal membuat user di Guacamole',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'User berhasil dibuat',
            'data' => $user,
            'generated_password' => $plainPassword
        ]);
    }
}