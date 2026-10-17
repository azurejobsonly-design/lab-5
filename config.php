<?php
// config.php - shared helpers used by every page

const CATEGORIES = ['Feature', 'Bug', 'Question', 'Other'];

// Read an environment variable (App Service app settings arrive this way)
function env_value(string $name, string $default = ''): string
{
    $v = getenv($name);
    if ($v === false || $v === '') {
        $v = $_SERVER[$name] ?? $_ENV[$name] ?? '';
    }
    return ($v === '' || $v === false) ? $default : (string)$v;
}

// Read a plain string from $_GET / $_POST / $_COOKIE safely
function input_str(array $source, string $key, string $default = ''): string
{
    $v = $source[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function app_version(): string  { return env_value('APP_VERSION', '1.0'); }
function instance_name(): string { return gethostname() ?: 'unknown'; }
function instance_id(): string   { return env_value('WEBSITE_INSTANCE_ID', 'unset'); }

// Where the data lives. Default = local temp folder of the current machine/container.
function data_dir(): string
{
    $dir = env_value('DATA_DIR', sys_get_temp_dir() . '/board-data');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function data_file(): string
{
    return data_dir() . '/requests.json';
}

// Read all requests (shared lock)
function load_requests(): array
{
    $file = data_file();
    if (!is_file($file)) {
        return [];
    }
    $fh = @fopen($file, 'r');
    if (!$fh) {
        return [];
    }
    flock($fh, LOCK_SH);
    $json = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode($json ?: '[]', true);
    return is_array($data) ? $data : [];
}

// Read -> change -> write, all under one exclusive lock.
// $change receives the array and must return the new array.
function update_requests(callable $change): bool
{
    $fh = @fopen(data_file(), 'c+');
    if (!$fh) {
        return false;
    }
    flock($fh, LOCK_EX);
    $data = json_decode(stream_get_contents($fh) ?: '[]', true);
    if (!is_array($data)) {
        $data = [];
    }
    $data = $change($data);
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return true;
}

// Search + category filter + sorting (used by the page AND the API)
function filter_requests(array $items, string $q, string $category, string $sort): array
{
    if ($q !== '') {
        $items = array_filter($items, function ($r) use ($q) {
            return stripos($r['title'], $q) !== false
                || stripos($r['description'], $q) !== false;
        });
    }
    if ($category !== '' && in_array($category, CATEGORIES, true)) {
        $items = array_filter($items, fn($r) => $r['category'] === $category);
    }
    $items = array_values($items);

    if ($sort === 'newest') {
        usort($items, fn($a, $b) => $b['id'] <=> $a['id']);
    } else { // default: most votes first, newer first on ties
        usort($items, fn($a, $b) => [$b['votes'], $b['id']] <=> [$a['votes'], $a['id']]);
    }
    return $items;
}

function board_url(array $params = []): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'index.php' . ($params ? '?' . http_build_query($params) : '');
}

// Info block used by the API (and handy for debugging)
function api_meta(): array
{
    return [
        'instance'    => instance_name(),
        'instance_id' => instance_id(),
        'version'     => app_version(),
        'data_dir'    => data_dir(),   // lab convenience only; remove in a real app
        'time_utc'    => gmdate('c'),
    ];
}

function page_header(string $title): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> - Requests board</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <strong>Requests board</strong>
  <nav>
    <a href="index.php">Board</a>
    <a href="api.php">API (JSON)</a>
    <a href="health.php">Health</a>
  </nav>
</header>
<main>
<?php
}

function page_footer(): void
{
    ?></main>
<footer>
  Instance: <strong><?= h(instance_name()) ?></strong>
  &middot; Instance ID: <?= h(substr(instance_id(), 0, 12)) ?>
  &middot; Version: <strong><?= h(app_version()) ?></strong>
  &middot; Rendered: <?= h(gmdate('H:i:s')) ?> UTC
</footer>
</body>
</html>
<?php
}