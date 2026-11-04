<?php
declare(strict_types=1);
define('APP_NAME', 'MikroTik Shortcut');
define('DATA_DIR', __DIR__ . '/data');
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('CONFIG_FILE', DATA_DIR . '/config.json');
define('MAX_ROUTERS', 10);
date_default_timezone_set('Asia/Jakarta');

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0750, true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0750, true);

if (!file_exists(CONFIG_FILE)) {
    $initial = [
        'branding' => ['logo' => '', 'banner' => '', 'title' => 'MikroTik Shortcut', 'subtitle' => 'Network Management Dashboard'],
        'shortcuts' => [
            ['label' => 'Billing', 'url' => '#', 'icon' => 'receipt'],
            ['label' => 'Mikhmon', 'url' => '#', 'icon' => 'monitor'],
            ['label' => 'Keuangan', 'url' => '#', 'icon' => 'wallet']
        ],
        'routers' => []
    ];
    file_put_contents(CONFIG_FILE, json_encode($initial, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function app_config(): array {
    $data = json_decode((string)file_get_contents(CONFIG_FILE), true);
    return is_array($data) ? $data : ['branding'=>[], 'shortcuts'=>[], 'routers'=>[]];
}
function save_config(array $config): void {
    $tmp = CONFIG_FILE . '.tmp';
    file_put_contents($tmp, json_encode($config, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    chmod($tmp, 0640);
    rename($tmp, CONFIG_FILE);
}
function json_response(array $data, int $status=200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
