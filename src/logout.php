<?php
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

$_SESSION = array();
session_destroy();
header('Location: login.php');
