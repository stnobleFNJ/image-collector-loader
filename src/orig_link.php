<?php
// (cafe24 등 NAS 에 접근 못 하는 서버) 원본 다운로드: 서명된 링크로 사내 원본 서버에 넘김
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';
require __DIR__ . '/inc/pickzip.php';

$member = require_member_page();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    http_response_code(400);
    exit('잘못된 요청입니다.');
}
if (empty($_origServer) || empty($_origSecret)) {
    zip_fail('사내 원본 서버가 설정되어 있지 않습니다.');
}

if (!in_office()) {
    zip_fail('사내망에서만 원본 다운로드가 가능합니다.');
}
$owner = pick_owner($member);  // 총괄 어드민은 다른 계정의 선택도 가능
if ($owner === null) {
    zip_fail('다운로드할 수 없는 계정입니다.');
}
$bs = int_list(isset($_POST['bs']) ? $_POST['bs'] : '');
$token = orig_token($member['mem_id'], $bs, $owner, zip_by_bs(isset($_POST['layout']) ? $_POST['layout'] : ''));
header('Location: ' . rtrim($_origServer, '/') . '/orig.php?t=' . rawurlencode($token));
