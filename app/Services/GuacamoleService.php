<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GuacamoleService
{
    protected $baseURL;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->baseURL = env('GUACAMOLE_URL');
        $this->password = env('GUACAMOLE_PASSWORD');
        $this->username = env('GUACAMOLE_USERNAME');
    }

    public function getToken()
    {
        $response = Http::asForm()->post($this->baseURL . '/api/tokens', [
            'username' => $this->username,
            'password' => $this->password
        ]);

        if ($response->failed()) {
            throw new Exception('Gagal Login Apache Guacamole');
        }
        return $response->json();
    }

    public function getUsers()
    {
        $auth = $this->getToken();
        $response = Http::get($this->baseURL . '/api/session/data/' . $auth['dataSource'] . '/users', [
            'token' => $auth['authToken']
        ]);

        if ($response->failed()) {
            throw new Exception('Gagal Mengambil User');
        }

        return $response->json();
    }

    public function createUser(array $data)
    {
        $auth = $this->getToken();

        $payload = [
            "username" => $data['username'],
            "password" => $data['password'],
            "attributes" => [
                "disabled" => "",
                "expired" => "",
                "access-window-start" => "",
                "access-window-end" => "",
                "valid-from" => "",
                "valid-until" => "",
                "timezone" => null,
                "guac-full-name" => $data['name'] ?? "",
                "guac-email-address" => $data['email'],
                "guac-organization" => "",
                "guac-organizational-role" => $data['role'] ?? "user"
            ]
        ];

        $response = Http::asJson()->post(
            $this->baseURL . '/api/session/data/' . $auth['dataSource'] . '/users?token=' . $auth['authToken'],
            $payload
        );
        Log::info('Guacamole raw response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            throw new Exception('Guacamole Error: ' . $response->body());
        }

        return $response->json();
    }
    public function createConnection(array $data)
    {
        $auth = $this->getToken();

        $payload = [
            "parentIdentifier" => "ROOT", // Langsung ke root
            "name" => $data['name'],
            "protocol" => "vnc",
            "parameters" => [
                "hostname" => $data['ip_address'],
                "port" => "5901",
                "password" => "password"
            ],
            "attributes" => [
                "max-connections" => "",
                "max-connections-per-user" => "",
                "weight" => "",
                "failover-only" => "",
                "guacd-port" => "",
                "guacd-encryption" => ""
            ]
        ];

        $response = Http::asJson()->post(
            $this->baseURL . '/api/session/data/' . $auth['dataSource'] . '/connections?token=' . $auth['authToken'],
            $payload
        );

        if ($response->failed()) {
            throw new Exception('Guacamole Connection Error: ' . $response->body());
        }

        return $response->json();
    }
    public function assignUserToConnection($username, $connectionIdentifier)
    {
        $auth = $this->getToken();

        $payload = [
            [
                "op" => "add",
                "path" => "/connectionGroupPermissions/ROOT", // Beri izin baca di level root
                "value" => "READ"
            ],
            [
                "op" => "add",
                "path" => "/connectionPermissions/" . $connectionIdentifier,
                "value" => "READ"
            ]
        ];

        $response = Http::asJson()->patch(
            $this->baseURL . '/api/session/data/' . $auth['dataSource'] . '/users/' . $username . '/permissions?token=' . $auth['authToken'],
            $payload
        );

        if ($response->failed()) {
            throw new Exception('Guacamole Permission Error: ' . $response->body());
        }

        return $response->successful();
    }
}

