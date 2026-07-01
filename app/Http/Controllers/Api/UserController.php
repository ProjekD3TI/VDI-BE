<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserByIdResource;
use App\Http\Resources\UserResource;
use App\Models\Angkatan;
use App\Models\User;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::with(['vms', 'angkatan'])
                ->where('role', 'user');

            // Search
            if ($request->filled('search')) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('nim', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $users = $query
                ->orderByDesc('created_at')
                ->paginate(10);

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
            ], 500);
        }
    }
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
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
                    'regex:/^[a-zA-Z0-9.-]+$/',
                    'regex:/^[a-zA-Z0-9]/',
                ],
                'email' => [
                    'required',
                    'email',
                    'unique:users,email',
                    'regex:/@student\.uns\.ac\.id$/'
                ],
                'angkatan_id' => [
                    'required',
                    'integer'
                ],
            ]);

            DB::beginTransaction();

            $user = User::create($validated);

            event(new Registered($user));

            $token = $user->createToken('vdi_auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'message' => 'Registrasi berhasil',
                'token' => $token,
                'user' => $user
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'error' => $e->errors()
            ], 422);

        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ], 500);
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