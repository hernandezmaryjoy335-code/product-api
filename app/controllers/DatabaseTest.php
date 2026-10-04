<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class DatabaseTest extends Controller
{
    public function index()
    {
        if (config_item('environment') !== 'development') {
            http_response_code(404);
            exit;
        }

        $this->call->database();

        $result = $this->db
            ->raw('SELECT 1 AS connection_ok')
            ->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');

        echo json_encode([
            'connected' => (int) $result['connection_ok'] === 1,
            'message' => 'Connected to Aiven MySQL'
        ]);
    }
}