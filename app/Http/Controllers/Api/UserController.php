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
use Illuminate\Support\Facades\Auth;
use Throwable;

class UserController extends Controller
{
    public function index()
    {
        try {
            $users = User::with(['vms', 'angkatan'])->where('role', 'user')->paginate(10);

            $paginatedResponse = $users->toArray();


            $paginatedResponse['data'] = UserResource::collection($users)->resolve();
            return response()->json([
                'message' => 'Successfully retrieved user data',
                'data' => $paginatedResponse
            ], 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while retrieving user data',
                'error' => $th->getMessage()
            ], $th->getCode());
        }
    }
    public function store(Request $request)
    {
        try {
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
                'message' => 'Successfully added user',
                'data' => $user,
            ], 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'An error occurred while creating the user',
                'error' => $th->getMessage()
            ], $th->getCode());
        }
    }

    public function getUserById($id)
    {
        try {
            $user = User::findOrFail($id);

            return response()->json([
                'message' => 'Successfully retrieved user data',
                'data' => new UserByIdResource($user)
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found',
                'erroe' => $e->getMessage()
            ], $e->getCode());
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'GagalFailed to get user',
                'error' => $th->getMessage()
            ], $th->getCode());
        }
    }
    public function destroy($id)
    {
        if (Auth::id() == $id) {
            return response()->json([
                'message' => 'You cannot delete your own account.'
            ], 403);
        }
        try {
            $data = User::with('vms')->findOrFail($id);

            if ($data->vms) {
                return response()->json([
                    'message' => 'The user cannot be deleted because it still has a VM.'
                ], 409);
            }

            $data->delete($id);

            return response()->json([
                'message' => 'Successfully deleted user.'
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found.',
                'error' => $e->getMessage()
            ], $e->getCode());
        } catch (Exception $th) {
            return response()->json([
                'message' => 'An error occurred on the server.',
                'error' => $th->getMessage()
            ], $th->getCode());
        }
    }
}