<?php
// 썸네일 이미지 (NAS_IMG.thumb)
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

if (empty($_SESSION['mem_id'])) {
    http_response_code(401);
    exit;
}
session_write_close();  // 썸네일 동시 요청이 세션 잠금으로 줄서지 않도록

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$etag = '"t' . $id . '"';
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

$rows = $id > 0 ? q("SELECT thumb FROM NAS_IMG WHERE img_id = ? AND thumb IS NOT NULL", 'i', array($id)) : array();
if (!$rows) {
    http_response_code(404);
    exit;
}

$data = $rows[0]['thumb'];
$info = @getimagesizefromstring($data);
header('Content-Type: ' . ($info ? $info['mime'] : 'image/jpeg'));
header('Content-Length: ' . strlen($data));
header('Cache-Control: private, max-age=2592000');
header('ETag: ' . $etag);
echo $data;
