<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
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


        // simpan ke database lokal
        $user = User::create([
            'username' => $request->username,
            'nim' => $request->nim,
            'name' => $request->name,
            'angkatan_id' => $request->angkatan_id,
            'role' => $request->role,
            'email' => $request->email,
        ]);

        return response()->json([
            'message' => 'User berhasil dibuat',
            'data' => $user,
        ]);
    }
}