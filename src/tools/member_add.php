<?php
// 회원 추가 / 비밀번호 변경 (CLI 전용)
// 사용법: php tools/member_add.php <아이디> <비밀번호> [이름] [이메일]
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

define('APP_INIT', true);
$_SESSION = array();
require __DIR__ . '/../db_config.php';

if ($argc < 3) {
    fwrite(STDERR, "사용법: php tools/member_add.php <아이디> <비밀번호> [이름] [이메일]\n");
    exit(1);
}
list(, $loginId, $passwd) = $argv;
$name  = isset($argv[3]) ? $argv[3] : '';
$email = isset($argv[4]) ? $argv[4] : '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($_dbHost, $_dbUser, $_dbPasswd, $_dbName);
$conn->set_charset('utf8mb4');

$hash = password_hash($passwd, PASSWORD_DEFAULT);
$st = $conn->prepare(
    "INSERT INTO NAS_MEMBER (login_id, passwd, name, email, created_at, updated_at, db_state)
     VALUES (?, ?, ?, ?, NOW(), NOW(), 'Y')
     ON DUPLICATE KEY UPDATE passwd = VALUES(passwd),
        name = IF(VALUES(name) = '', name, VALUES(name)),
        email = IF(VALUES(email) = '', email, VALUES(email)),
        updated_at = NOW(), db_state = 'Y'");
$st->bind_param('ssss', $loginId, $hash, $name, $email);
$st->execute();
echo ($st->affected_rows === 1 ? "추가됨" : "갱신됨") . ": {$loginId}\n";
