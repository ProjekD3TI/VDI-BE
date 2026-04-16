<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GuacamoleService;
use Exception;
use Illuminate\Http\Request;

class GuacamoleController extends Controller
{
    protected $guac;

    public function __construct(GuacamoleService $guac)
    {
        $this->guac = $guac;
    }

    public function getUsers()
    {
        try {
            $users = $this->guac->getUsers();

            return response()->json([
                'success' => true,
                'data' => $users
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
