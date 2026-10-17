<?php
header('Content-Type: application/json');

$dataDir  = '/home/data';          // CHANGE: the folder your app saves posts in
$dataFile = $dataDir . '/posts.json'; // CHANGE: your app's data file
$checks = [];

// 1. Folder exists and is writable
$checks['data_dir_writable'] = is_dir($dataDir) && is_writable($dataDir);

// 2. Write, read back, delete a small file
$ok = false;
if ($checks['data_dir_writable']) {
    $probe = $dataDir . '/.health_' . getmypid();
    $token = bin2hex(random_bytes(4));
    $ok = (@file_put_contents($probe, $token) !== false)
          && (@file_get_contents($probe) === $token);
    @unlink($probe);
}
$checks['write_read_roundtrip'] = $ok;

// 3. Main data file is valid JSON (only if it exists)
if (file_exists($dataFile)) {
    json_decode(file_get_contents($dataFile));
    $checks['data_file_valid_json'] = (json_last_error() === JSON_ERROR_NONE);
}

// 4. Manual kill switch for failure drills (this instance only)
$checks['not_drained'] = !file_exists('/tmp/unhealthy.flag');

$healthy = !in_array(false, $checks, true);
http_response_code($healthy ? 200 : 503);

echo json_encode([
    'status'   => $healthy ? 'healthy' : 'unhealthy',
    'instance' => gethostname(),
    'checks'   => $checks,
    'time'     => date('c'),
], JSON_PRETTY_PRINT);