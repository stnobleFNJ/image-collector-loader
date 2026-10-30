<?php
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

header('Location: ' . (current_member() ? 'check.php' : 'login.php'));
