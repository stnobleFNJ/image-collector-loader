<?php
// 서버 환경 점검 (임시) — 확인이 끝나면 서버에서 반드시 삭제하세요.
//   사용: https://도메인/imgCheck/diag.php?key=fnjdiag2026
if (!isset($_GET['key']) || $_GET['key'] !== 'fnjdiag2026') {
    http_response_code(404);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
define('APP_INIT', true);
require __DIR__ . '/db_config.php';

function line($k, $v) { echo str_pad($k, 26) . ': ' . $v . "\n"; }
function yn($b) { return $b ? 'OK' : '없음'; }

line('PHP 버전', PHP_VERSION);
line('mysqli 확장', yn(extension_loaded('mysqli')));
line('mysqlnd (get_result)', yn(extension_loaded('mysqlnd')));
line('zip 확장', yn(class_exists('ZipArchive')));
line('mbstring 확장', yn(extension_loaded('mbstring')));
line('session 저장 경로', (session_save_path() ?: '(기본)') . ' / 쓰기 ' . yn(is_writable(session_save_path() ?: sys_get_temp_dir())));
line('임시 폴더', sys_get_temp_dir() . ' / 쓰기 ' . yn(is_writable(sys_get_temp_dir())));
line('설정 DB 호스트', $_dbHost);
echo "\n";

mysqli_report(MYSQLI_REPORT_OFF);
foreach (array_unique(array($_dbHost, 'localhost')) as $host) {
    $t = microtime(true);
    $c = @new mysqli($host, $_dbUser, $_dbPasswd, $_dbName);
    $ms = round((microtime(true) - $t) * 1000);
    if ($c->connect_errno) {
        line("접속 [$host]", "실패 ({$ms}ms) #{$c->connect_errno} {$c->connect_error}");
        continue;
    }
    $c->set_charset('utf8mb4');
    $r = $c->query("SELECT COUNT(*) FROM NAS_MEMBER");
    line("접속 [$host]", "성공 ({$ms}ms), 서버 " . $c->server_info . ', NAS_MEMBER ' . ($r ? $r->fetch_row()[0] . '명' : '조회 실패: ' . $c->error));
    $c->close();
}
