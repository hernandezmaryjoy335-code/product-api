<?php

class AccountSetup extends Controller
{
    public function __construct()
    {
        parent::__construct();

        if (PHP_SAPI !== 'cli') {
            http_response_code(404);
            exit;
        }
    }

    public function index()
    {
        $username = trim((string) getenv('SEED_USERNAME'));
        $email = trim((string) getenv('SEED_EMAIL'));
        $password = (string) getenv('SEED_PASSWORD');

        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
            echo "Invalid username. Use 3-50 letters, numbers, or underscores. No spaces.\n";
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "Invalid email address.\n";
            return;
        }

        if (strlen($password) < 12 || strlen($password) > 72) {
            echo "Password must be 12-72 bytes.\n";
            return;
        }

        try {
            $this->call->database();

            $existing = $this->db->raw(
                'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1',
                [$username, $email]
            )->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                echo "Username or email already exists. Use your existing login.\n";
                return;
            }

            $this->db->raw(
                'INSERT INTO users
                    (username, email, password, role, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    $username,
                    $email,
                    password_hash($password, PASSWORD_BCRYPT),
                    'user',
                    1
                ]
            );

            echo "Account created successfully.\n";
        } catch (Throwable $error) {
            echo "Account creation failed: " . $error->getMessage() . "\n";
        }
    }
}