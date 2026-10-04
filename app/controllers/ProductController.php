<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $this->call->library('api');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            $this->api->respond(null, 204);
            exit;
        }

        $this->call->database();

        $auth = $this->api->require_jwt();

        $user = $this->db->raw(
            'SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1',
            [$auth['sub']]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->fail('Unauthorized.', 401);
        }
    }

    private function fail($message, $status)
    {
        $this->api->respond_error($message, $status);
        exit;
    }

    private function findProduct($id)
    {
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->fail('Invalid product ID.', 422);
        }

        $product = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at
             FROM products WHERE id = ? LIMIT 1',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $this->fail('Product not found.', 404);
        }

        return $product;
    }

    private function readProduct()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            $this->fail('Send a valid JSON request.', 400);
        }

        $name = $input['product_name'] ?? '';
        $description = $input['description'] ?? '';
        $price = $input['price'] ?? null;
        $quantity = $input['quantity'] ?? null;

        if (
            !is_string($name) ||
            !preg_match('/^.{1,100}$/us', trim($name))
        ) {
            $this->fail('Product name must be 1-100 characters.', 422);
        }

        if (!is_string($description) || strlen($description) > 65535) {
            $this->fail('Description must be text up to 65535 bytes.', 422);
        }

        if (
            !(is_string($price) || is_int($price) || is_float($price)) ||
            !preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $price)
        ) {
            $this->fail(
                'Price must be 0-99999999.99 with at most two decimal places.',
                422
            );
        }

        if (
            !(is_string($quantity) || is_int($quantity)) ||
            !preg_match('/^\d{1,10}$/', (string) $quantity) ||
            (float) $quantity > 2147483647
        ) {
            $this->fail('Quantity must be a whole number from 0 to 2147483647.', 422);
        }

        return [
            trim($name),
            trim($description),
            (string) $price,
            (int) $quantity
        ];
    }

    public function index()
    {
        $this->api->require_method('GET');

        $products = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at
             FROM products ORDER BY id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond(['products' => $products]);
    }

    public function store()
    {
        $this->api->require_method('POST');
        $values = $this->readProduct();

        $this->db->raw(
            'INSERT INTO products
                (product_name, description, price, quantity, created_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)',
            $values
        );

        $result = $this->db->raw(
            'SELECT LAST_INSERT_ID() AS id'
        )->fetch(PDO::FETCH_ASSOC);

        $this->api->respond([
            'message' => 'Product added successfully.',
            'product' => $this->findProduct($result['id'])
        ], 201);
    }

    public function update($id)
    {
        $this->api->require_method('PUT');
        $this->findProduct($id);

        $values = $this->readProduct();
        $values[] = $id;

        $this->db->raw(
            'UPDATE products
             SET product_name = ?, description = ?, price = ?, quantity = ?
             WHERE id = ?',
            $values
        );

        $this->api->respond([
            'message' => 'Product updated successfully.',
            'product' => $this->findProduct($id)
        ]);
    }

    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->findProduct($id);

        $this->db->raw(
            'DELETE FROM products WHERE id = ?',
            [$id]
        );

        $this->api->respond([
            'message' => 'Product deleted successfully.'
        ]);
    }

    public function options()
    {
        $this->api->respond(null, 204);
    }
}