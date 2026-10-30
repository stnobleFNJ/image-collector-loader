<?php
// 확인 페이지 API
//   GET  act=items  bs=1,2 offset=0  : 선택 사업의 썸네일 목록
//   GET  act=picks  bs=1,2 [mem=3]   : 내가 선택한 썸네일 (사업 필터 동일 적용, mem 은 총괄 어드민만)
//   POST act=toggle id=123 on=1|0    : 썸네일 선택/해제
//   POST act=clear  bs=1,2           : 현재 필터의 선택 전체 해제
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

const PAGE_SIZE = 120;

$member = require_member_api();
session_write_close();
$memId = (int)$member['mem_id'];
$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : '';
$bs = int_list(isset($_REQUEST['bs']) ? $_REQUEST['bs'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_ok()) {
    json_out(array('ok' => false, 'error' => '페이지가 만료되었습니다. 새로고침해 주세요.'), 400);
}

// bs_id 는 정수만 남겼으므로 IN 절에 직접 사용
function bs_in($bs) {
    return implode(',', $bs);
}

if ($act === 'items') {
    if (!$bs) {
        json_out(array('ok' => true, 'items' => array(), 'total' => 0, 'more' => false));
    }
    $offset = max(0, isset($_GET['offset']) ? (int)$_GET['offset'] : 0);
    $where = "i.db_state = 'Y' AND i.thumb IS NOT NULL AND i.bs_id IN (" . bs_in($bs) . ")";

    $total = null;
    if ($offset === 0) {
        $r = q("SELECT COUNT(*) AS n FROM NAS_IMG i WHERE $where");
        $total = (int)$r[0]['n'];
    }
    $rows = q("SELECT i.img_id AS id, i.file_name AS name, i.bs_name AS bs, i.bs_year AS yr,
                      (p.mem_id IS NOT NULL) AS picked
               FROM NAS_IMG i
               LEFT JOIN NAS_MEMBER_PICK p ON p.mem_id = ? AND p.path_hash = i.path_hash
               WHERE $where
               ORDER BY i.bs_year DESC, i.img_id
               LIMIT ?, ?",
        'iii', array($memId, $offset, PAGE_SIZE + 1));

    $more = count($rows) > PAGE_SIZE;
    if ($more) {
        array_pop($rows);
    }
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['picked'] = (bool)$row['picked'];
    }
    json_out(array('ok' => true, 'items' => $rows, 'total' => $total, 'more' => $more));
}

if ($act === 'picks') {
    // 총괄 어드민은 mem=회원번호 로 다른 계정의 선택을 조회 (조회만 가능)
    $owner = pick_owner($member);
    if ($owner === null) {
        json_out(array('ok' => false, 'error' => '조회할 수 없는 계정입니다.'), 403);
    }
    $filter = $bs ? " AND i.bs_id IN (" . bs_in($bs) . ")" : '';
    $rows = q("SELECT i.img_id AS id, i.file_name AS name, i.bs_id AS bsid, i.bs_name AS bs, i.bs_year AS yr
               FROM NAS_MEMBER_PICK p
               JOIN NAS_IMG i ON i.path_hash = p.path_hash AND i.db_state = 'Y'
               WHERE p.mem_id = ? $filter
               ORDER BY p.created_at DESC",
        'i', array($owner));
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['bsid'] = (int)$row['bsid'];
    }
    json_out(array('ok' => true, 'items' => $rows));
}

if ($act === 'toggle' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $on = !empty($_POST['on']);
    $img = q("SELECT img_id, path_hash FROM NAS_IMG WHERE img_id = ? AND db_state = 'Y'", 'i', array($id));
    if (!$img) {
        json_out(array('ok' => false, 'error' => '이미지를 찾을 수 없습니다.'), 404);
    }
    if ($on) {
        q("INSERT IGNORE INTO NAS_MEMBER_PICK (mem_id, path_hash, img_id, created_at) VALUES (?, ?, ?, NOW())",
            'isi', array($memId, $img[0]['path_hash'], $id));
    } else {
        q("DELETE FROM NAS_MEMBER_PICK WHERE mem_id = ? AND path_hash = ?", 'is', array($memId, $img[0]['path_hash']));
    }
    json_out(array('ok' => true, 'id' => $id, 'on' => $on));
}

if ($act === 'clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($bs) {
        $n = q("DELETE p FROM NAS_MEMBER_PICK p
                JOIN NAS_IMG i ON i.path_hash = p.path_hash AND i.db_state = 'Y'
                WHERE p.mem_id = ? AND i.bs_id IN (" . bs_in($bs) . ")", 'i', array($memId));
    } else {
        $n = q("DELETE FROM NAS_MEMBER_PICK WHERE mem_id = ?", 'i', array($memId));
    }
    json_out(array('ok' => true, 'deleted' => $n));
}

json_out(array('ok' => false, 'error' => '알 수 없는 요청입니다.'), 400);
