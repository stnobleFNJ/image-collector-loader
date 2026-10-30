<?php
// 내가 선택한 이미지 ZIP 생성/전송 (download.php, orig.php 공용)
if (!defined('APP_INIT')) { http_response_code(403); exit; }

const MAX_THUMBS = 1000;
const MAX_ORIGS  = 500;
const MAX_ORIG_BYTES = 3221225472;  // 원본 합계 3GB

function zip_fail($msg) {
    echo '<!DOCTYPE html><meta charset="utf-8"><script>alert(' . json_encode($msg, JSON_UNESCAPED_UNICODE) . ');history.length > 1 ? history.back() : window.close();</script>';
    exit;
}

// 파일/폴더 이름에 쓸 수 없는 문자 정리
function safe_name($s) {
    $s = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]/u', '_', (string)$s);
    return trim($s, " .") === '' ? '_' : trim($s, " .");
}

// 선택 목록 (사업 필터 동일 적용)
function pick_rows($memId, $bs, $orig) {
    $filter = $bs ? " AND i.bs_id IN (" . implode(',', $bs) . ")" : '';
    $cols = $orig ? 'i.folder_path, i.size_kb' : 'i.thumb';
    return q("SELECT i.img_id, i.file_name, i.bs_name, $cols
              FROM NAS_MEMBER_PICK p
              JOIN NAS_IMG i ON i.path_hash = p.path_hash AND i.db_state = 'Y'
              WHERE p.mem_id = ? " . ($orig ? '' : 'AND i.thumb IS NOT NULL') . " $filter
              ORDER BY i.bs_name, i.img_id
              LIMIT " . ($orig ? MAX_ORIGS : MAX_THUMBS),
        'i', array((int)$memId));
}

// 압축 구성: 기본은 한 폴더, layout=bs 이면 사업명 폴더별
function zip_by_bs($v) {
    return $v === 'bs';
}

// $tag: 파일명에 붙일 계정 아이디 (어드민이 다른 계정 선택을 받을 때)
// $byBs: true 면 사업명 폴더를 만들어 나눠 담고, false 면 한 폴더에 모두 담음
function send_pick_zip($memId, $bs, $orig, $tag = '', $byBs = false) {
    $rows = pick_rows($memId, $bs, $orig);
    if (!$rows) {
        zip_fail('다운로드할 이미지가 없습니다.');
    }

    set_time_limit(0);
    $tmp = tempnam(sys_get_temp_dir(), 'fnjzip');
    register_shutdown_function(function () use ($tmp) { @unlink($tmp); });  // 중간에 연결이 끊겨도 삭제
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        exit('ZIP 파일을 만들 수 없습니다.');
    }

    $missing = array();
    $used = array();
    $total = 0;
    foreach ($rows as $r) {
        $dir = $byBs ? safe_name($r['bs_name']) . '/' : '';
        if ($orig) {
            $src = nas_path($r['folder_path'], $r['file_name']);
            $size = is_file($src) ? filesize($src) : false;
            if ($size === false) {
                $missing[] = $r['folder_path'] . '\\' . $r['file_name'];
                continue;
            }
            if ($total + $size > MAX_ORIG_BYTES) {
                $missing[] = '(용량 제한으로 제외) ' . $r['file_name'];
                continue;
            }
            $total += $size;
            // 같은 폴더 안에 같은 파일명이 있으면 img_id 를 붙여 구분 (한 폴더 모드는 사업이 달라도 겹칠 수 있음)
            $entry = $dir . safe_name($r['file_name']);
            if (isset($used[mb_strtolower($entry)])) {
                $entry = $dir . safe_name(pathinfo($r['file_name'], PATHINFO_FILENAME)) . '_' . $r['img_id']
                    . '.' . safe_name(pathinfo($r['file_name'], PATHINFO_EXTENSION));
            }
            $used[mb_strtolower($entry)] = true;
            $zip->addFile($src, $entry);
            $zip->setCompressionName($entry, ZipArchive::CM_STORE);  // 이미지는 이미 압축되어 있어 저장만
        } else {
            $base = pathinfo($r['file_name'], PATHINFO_FILENAME);
            $zip->addFromString($dir . $r['img_id'] . '_' . safe_name($base) . '.jpg', $r['thumb']);
        }
    }
    if ($missing) {
        $zip->addFromString('_누락된_파일.txt', "NAS 에서 찾지 못했거나 제외된 파일입니다.\r\n\r\n" . implode("\r\n", $missing) . "\r\n");
    }
    if (count($missing) === count($rows)) {
        $zip->close();
        @unlink($tmp);
        zip_fail('NAS 에서 원본 파일을 찾지 못했습니다.');
    }
    if (!$zip->close()) {
        @unlink($tmp);
        zip_fail('ZIP 파일을 만드는 중 오류가 발생했습니다: ' . $zip->getStatusString());
    }

    $stamp = date('Ymd_His');
    $label = ($orig ? '원본' : '썸네일') . ($tag !== '' ? '_' . safe_name($tag) : '');
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header("Content-Disposition: attachment; filename=\"FNJ_images_{$stamp}.zip\"; filename*=UTF-8''" . rawurlencode("FNJ_{$label}_{$stamp}.zip"));
    header('Cache-Control: no-store');
    // 큰 ZIP 도 메모리 제한에 걸리지 않도록 버퍼를 끄고 1MB 씩 전송
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $fp = fopen($tmp, 'rb');
    while (!feof($fp) && !connection_aborted()) {
        echo fread($fp, 1048576);
        flush();
    }
    fclose($fp);
    unlink($tmp);
    exit;
}

/* ---------- 사내 원본 서버 연결용 서명 토큰 ---------- */

function b64url($s) {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64url_dec($s) {
    return base64_decode(strtr($s, '-_', '+/'));
}

// cafe24 에서 발급: 회원·사업 필터·만료시각을 HMAC 으로 서명
//   $owner: 선택 목록의 주인 (어드민이 다른 계정 선택을 받을 때, 기본은 본인)
//   $byBs : 사업명 폴더별 압축 여부
function orig_token($memId, $bs, $owner = 0, $byBs = false, $ttl = 300) {
    global $_origSecret;
    $d = array('m' => (int)$memId, 'b' => array_values($bs), 'e' => time() + $ttl);
    if ($byBs) {
        $d['g'] = 1;
    }
    if ($owner && (int)$owner !== (int)$memId) {
        $d['o'] = (int)$owner;
    }
    $payload = b64url(json_encode($d));
    return $payload . '.' . b64url(hash_hmac('sha256', $payload, $_origSecret, true));
}

// 사내 서버에서 검증: 성공 시 array('m' => 요청 회원, 'o' => 선택 주인, 'b' => bs[], 'g' => 사업별 폴더), 실패 시 오류 문자열
function orig_token_verify($token) {
    global $_origSecret;
    if (empty($_origSecret) || !is_string($token) || substr_count($token, '.') !== 1) {
        return '잘못된 링크입니다.';
    }
    list($payload, $sig) = explode('.', $token);
    if (!hash_equals(b64url(hash_hmac('sha256', $payload, $_origSecret, true)), $sig)) {
        return '잘못된 링크입니다.';
    }
    $d = json_decode(b64url_dec($payload), true);
    if (!is_array($d) || empty($d['m']) || !isset($d['e'])) {
        return '잘못된 링크입니다.';
    }
    if ($d['e'] < time()) {
        return '링크 유효시간(5분)이 지났습니다. 이미지 확인 화면에서 다시 눌러 주세요.';
    }
    return array('m' => (int)$d['m'], 'o' => empty($d['o']) ? (int)$d['m'] : (int)$d['o'],
                 'b' => int_list(isset($d['b']) ? $d['b'] : array()), 'g' => !empty($d['g']));
}
