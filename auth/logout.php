<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
destroySession();
header('Location: /cookstream/');
exit;
