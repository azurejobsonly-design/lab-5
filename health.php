<?php
require __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Instance: ' . instance_name());
http_response_code(200);
echo 'ok';