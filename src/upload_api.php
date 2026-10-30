<?php
// 신규 이미지 등록 API (로컬 서버 전용)
//   POST act=upload bs=사업 file=원본 mtime=파일 수정시각(ms)
//     : NAS 업로드 폴더\사업명 에 원본 저장 (폴더 없으면 생성) + 썸네일 만들어 NAS_IMG 등록
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';
require __DIR__ . '/inc/upload.php';

$member = require_member_api();
session_write_close();

// post_max_size 를 넘으면 $_POST / $_FILES 가 모두 비어서 들어옴
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !empty($_SERVER['CONTENT_LENGTH'])) {
    json_out(array('ok' => false, 'error' => '파일이 너무 큽니다.'), 413);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || (isset($_POST['act']) ? $_POST['act'] : '') !== 'upload') {
    json_out(array('ok' => false, 'error' => '알 수 없는 요청입니다.'), 400);
}
if (!csrf_ok()) {
    json_out(array('ok' => false, 'error' => '페이지가 만료되었습니다. 새로고침해 주세요.'), 400);
}
$block = upload_block_reason();
if ($block !== '') {
    json_out(array('ok' => false, 'error' => $block), 403);
}

$bsId = isset($_POST['bs']) ? (int)$_POST['bs'] : 0;
$rows = q("SELECT bs_id, bs_name, bs_type, history_no, load_id FROM NAS_BS WHERE bs_id = ? AND db_state = 'Y'", 'i', array($bsId));
if (!$rows) {
    json_out(array('ok' => false, 'error' => '사업을 찾을 수 없습니다. 새로고침 후 다시 선택해 주세요.'), 404);
}
$bs = $rows[0];

$f = isset($_FILES['file']) ? $_FILES['file'] : null;
if (!$f || is_array($f['error'])) {
    json_out(array('ok' => false, 'error' => '파일이 없습니다.'), 400);
}
if ($f['error'] !== UPLOAD_ERR_OK) {
    json_out(array('ok' => false, 'error' => upload_err_msg($f['error'])), 400);
}
$origName = (string)$f['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, UPLOAD_EXTS, true)) {
    json_out(array('ok' => false, 'error' => '지원하지 않는 형식입니다. (' . implode(', ', UPLOAD_EXTS) . ')'), 400);
}

// 썸네일은 서버 임시 파일로 먼저 만듦 (실패하면 NAS 에 저장하지 않음)
$thumb = make_thumb($f['tmp_name']);
if (is_string($thumb)) {
    json_out(array('ok' => false, 'error' => $thumb), 400);
}

// 업로드 폴더\사업명 (없으면 생성)
$folder = rtrim($_uploadRoot, '\\/') . '\\' . safe_name($bs['bs_name']);
$dir = nas_local($folder);
if (!is_dir($dir) && !@mkdir($dir) && !is_dir($dir)) {
    json_out(array('ok' => false, 'error' => '사업 폴더를 만들 수 없습니다: ' . $folder), 500);
}

$base = mb_substr(safe_name(pathinfo($origName, PATHINFO_FILENAME)), 0, 180);
$name = unique_name($folder, $base, $ext);
$dest = nas_path($folder, $name);
if (!@move_uploaded_file($f['tmp_name'], $dest)) {
    json_out(array('ok' => false, 'error' => '원본을 NAS 에 저장하지 못했습니다: ' . $folder), 500);
}

// 연도 / 수정일은 원본 파일의 수정시각 기준 (없거나 이상하면 오늘)
$mtime = isset($_POST['mtime']) ? (int)floor((float)$_POST['mtime'] / 1000) : 0;
if ($mtime <= 0 || $mtime > time() + 86400) {
    $mtime = time();
}
$sizeKb = (int)round($f['size'] / 1024);
$history = is_numeric($bs['history_no']) ? (int)$bs['history_no'] : null;

try {
    q("INSERT INTO NAS_IMG (bs_id, bs_name, bs_year, bs_type, history_no, folder_path, file_name, file_ext, size_kb,
                            mod_date, copy_cnt, path_hash, created_at, updated_at, db_state,
                            img_w, img_h, thumb, thumb_w, thumb_h, thumb_at, load_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW(), 'Y', ?, ?, ?, ?, ?, NOW(), ?)",
        'isisisssissiisiii',
        array((int)$bs['bs_id'], $bs['bs_name'], (int)date('Y', $mtime), $bs['bs_type'], $history,
              $folder, $name, $ext, $sizeKb, date('Y-m-d', $mtime), path_hash($folder, $name),
              $thumb['w'], $thumb['h'], $thumb['data'], $thumb['tw'], $thumb['th'], (int)$bs['load_id']));
} catch (Exception $e) {
    @unlink($dest);  // DB 등록에 실패하면 NAS 에 남기지 않음
    throw $e;
}
$id = (int)db()->insert_id;

q("UPDATE NAS_BS SET image_cnt = IFNULL(image_cnt, 0) + 1, size_mb = IFNULL(size_mb, 0) + ?, updated_at = NOW() WHERE bs_id = ?",
    'di', array(round($f['size'] / 1048576, 1), (int)$bs['bs_id']));

json_out(array(
    'ok'      => true,
    'id'      => $id,
    'name'    => $name,
    'renamed' => $name !== $origName,
    'folder'  => $folder,
    'w'       => $thumb['w'],
    'h'       => $thumb['h'],
));
