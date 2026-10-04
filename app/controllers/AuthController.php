<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $this->call->library('api');
        $this->call->database();
    }

    public function login()
    {
        $this->api->require_method('POST');

        $this->api->rate_limit(
            'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            10,
            60
        );

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            $this->api->respond_error('Send a valid JSON request.', 400);
            return;
        }

        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (!is_string($username) || !is_string($password)) {
            $this->api->respond_error(
                'Username and password must be text.',
                422
            );
            return;
        }

        $username = trim($username);

        if ($username === '' || $password === '') {
            $this->api->respond_error(
                'Username and password are required.',
                422
            );
            return;
        }

        $user = $this->db->raw(
            'SELECT id, username, email, password, role, is_active
             FROM users
             WHERE username = ?
             LIMIT 1',
            [$username]
        )->fetch(PDO::FETCH_ASSOC);

        if (
            !$user ||
            !password_verify($password, $user['password']) ||
            (int) $user['is_active'] !== 1
        ) {
            $this->api->respond_error(
                'Invalid username or password.',
                401
            );
            return;
        }

        $tokens = $this->api->issue_tokens([
            'id' => $user['id'],
            'role' => $user['role']
        ]);

        $this->api->respond([
            'message' => 'Login successful.',
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'token_type' => $tokens['token_type']
        ]);
    }

    public function profile()
    {
        $auth = $this->api->require_jwt();

        $user = $this->db->raw(
            'SELECT id, username, email, role
             FROM users
             WHERE id = ? AND is_active = 1
             LIMIT 1',
            [$auth['sub']]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->api->respond_error('Unauthorized.', 401);
            return;
        }

        $this->api->respond(['user' => $user]);
    }

    public function logout()
    {
        $this->api->require_method('POST');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            $this->api->respond_error('Send a valid JSON request.', 400);
            return;
        }

        $refreshToken = $input['refresh_token'] ?? '';

        if (!is_string($refreshToken) || $refreshToken === '') {
            $this->api->respond_error('Refresh token is required.', 422);
            return;
        }

        $this->api->revoke_refresh_token($refreshToken);

        $this->api->respond([
            'message' => 'Logged out successfully.'
        ]);
    }

    public function options()
    {
        $this->api->respond(null, 204);
    }
}