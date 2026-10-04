<?php

class Migration
{
    public static $command = 'migration';

    public static $description = 'Manage database migrations';

    public static $arguments = [
        '[action]' => 'run, create-migration, rollback, rollback-all, refresh, status',
        '[name]' => 'Migration name'
    ];

    protected static $route_map = [
        'run' => 'migrate',
        'create-migration' => 'create-migration',
        'rollback' => 'rollback',
        'rollback-all' => 'rollback-all',
        'refresh' => 'refresh',
        'status' => 'status'
    ];

    public function handle($action = null, array $flags = [], $name = null)
    {
        $action = $action ?? 'run';

        if (!isset(static::$route_map[$action])) {
            echo "Unknown migration action: {$action}" . PHP_EOL;
            exit(1);
        }

        $route = static::$route_map[$action];

        if ($action === 'create-migration') {
            if (!$name || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                echo "Use a name such as create_products_table." . PHP_EOL;
                exit(1);
            }

            $route .= '/' . $name;
        }

        $index = PUBLIC_DIR . 'index.php';

        if (!is_file($index)) {
            echo "Cannot find public/index.php." . PHP_EOL;
            exit(1);
        }

        $command = sprintf(
            '%s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($index),
            escapeshellarg($route)
        );

        passthru($command, $exitCode);
        exit($exitCode);
    }
}