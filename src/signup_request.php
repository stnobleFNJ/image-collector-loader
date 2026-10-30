<?php
// 회원가입 요청 메일 발송
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok()) {
    json_out(array('ok' => false, 'error' => '잘못된 요청입니다. 새로고침 후 다시 시도해 주세요.'), 400);
}

$name    = trim(isset($_POST['name']) ? (string)$_POST['name'] : '');
$wantId  = trim(isset($_POST['want_id']) ? (string)$_POST['want_id'] : '');
$email   = trim(isset($_POST['email']) ? (string)$_POST['email'] : '');
$comment = trim(isset($_POST['comment']) ? (string)$_POST['comment'] : '');

if ($name === '' || $wantId === '' || $comment === '') {
    json_out(array('ok' => false, 'error' => '모든 항목을 입력해 주세요.'));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(array('ok' => false, 'error' => '이메일 형식이 올바르지 않습니다.'));
}
if (mb_strlen($name) > 50 || mb_strlen($wantId) > 50 || mb_strlen($comment) > 2000) {
    json_out(array('ok' => false, 'error' => '입력 내용이 너무 깁니다.'));
}
if (!empty($_SESSION['signup_at']) && time() - $_SESSION['signup_at'] < 60) {
    json_out(array('ok' => false, 'error' => '잠시 후 다시 시도해 주세요.'));
}

$host = preg_replace('/[^a-z0-9.\-]/i', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
$host = preg_replace('/^www\./i', '', $host);

$subject = '[' . APP_TITLE . '] 회원가입 요청 - ' . $name;
$body = "회원가입 요청이 접수되었습니다.\n\n"
    . "이름: {$name}\n"
    . "희망 아이디: {$wantId}\n"
    . "이메일: {$email}\n"
    . "요청 시각: " . date('Y-m-d H:i:s') . "\n"
    . "접속 IP: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . "\n\n"
    . "[코멘트]\n{$comment}\n\n"
    . "계정 생성: php tools/member_add.php {$wantId} <비밀번호> \"{$name}\" {$email}\n";

$headers = "From: =?UTF-8?B?" . base64_encode(APP_TITLE) . "?= <noreply@{$host}>\r\n"
    . "Reply-To: {$email}\r\n"
    . "MIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\n"
    . "Content-Transfer-Encoding: base64";

$sent = @mail(SIGNUP_MAIL, '=?UTF-8?B?' . base64_encode($subject) . '?=', chunk_split(base64_encode($body)), $headers);

if (!$sent) {
    json_out(array('ok' => false, 'error' => '메일 발송에 실패했습니다. 관리자(' . SIGNUP_MAIL . ')에게 직접 연락해 주세요.'));
}
$_SESSION['signup_at'] = time();
json_out(array('ok' => true));
