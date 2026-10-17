<?php
// Self-contained deep health check: no paths to edit.
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Cache-Control: no-store');

function check(callable $fn): array {
    $start = microtime(true);
    try {
        $detail = $fn();
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
        $detail = $e->getMessage();
    }
    return ['ok' => $ok, 'detail' => $detail,
            'ms' => round((microtime(true) - $start) * 1000, 1)];
}

// Actually writes, reads back, and deletes a file (more reliable than is_writable()).
function roundtrip(string $dir): string {
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('cannot create folder');
    }
    $file  = $dir . '/.hc_' . getmypid() . '_' . bin2hex(random_bytes(3));
    $token = bin2hex(random_bytes(4));
    if (@file_put_contents($file, $token) === false) {
        throw new RuntimeException('write failed');
    }
    $read = @file_get_contents($file);
    @unlink($file);
    if ($read !== $token) {
        throw new RuntimeException('read-back mismatch');
    }
    return 'ok';
}

$results = [];

// Storage local to THIS instance
$results['local_tmp'] = check(fn() => roundtrip(sys_get_temp_dir()));

// Storage shared by ALL instances
$results['shared_home'] = check(fn() => roundtrip('/home/healthcheck'));

// The app's own files are present
$results['app_files'] = check(function () {
    if (!is_readable(__DIR__ . '/index.php')) {
        throw new RuntimeException('index.php not readable');
    }
    return 'ok';
});

// Manual kill switch for drills (this instance only)
$results['drain_switch'] = check(function () {
    if (file_exists('/tmp/unhealthy.flag')) {
        throw new RuntimeException('drain flag present');
    }
    return 'ok';
});

// Optional: set this to your app's data file to also check it
$appDataFile = null;   // e.g. '/home/data/posts.json'
if ($appDataFile !== null) {
    $results['app_data'] = check(function () use ($appDataFile) {
        if (!file_exists($appDataFile)) {
            throw new RuntimeException('data file missing');
        }
        json_decode((string) file_get_contents($appDataFile));
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('invalid JSON');
        }
        return 'ok';
    });
}

$healthy = true;
foreach ($results as $r) {
    if (!$r['ok']) { $healthy = false; break; }
}

http_response_code($healthy ? 200 : 503);
echo json_encode([
    'status'   => $healthy ? 'healthy' : 'unhealthy',
    'instance' => gethostname(),
    'php'      => PHP_VERSION,
    'checks'   => $results,
    'time'     => date('c'),
], JSON_PRETTY_PRINT);