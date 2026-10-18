php
<?php
if (file_exists('/tmp/unhealthy.flag')) { http_response_code(500); echo 'unhealthy'; exit; }
echo 'ok';