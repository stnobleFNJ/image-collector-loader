<?php
// 신규 이미지 등록: NAS 사업 폴더에 원본 저장 + 썸네일 생성 (upload.php, upload_api.php 공용)
if (!defined('APP_INIT')) { http_response_code(403); exit; }

const THUMB_MAX   = 320;  // 썸네일 긴 변 (적재 프로그램과 동일)
const UPLOAD_EXTS = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp');

// 등록할 수 없는 이유 (가능하면 '')
function upload_block_reason() {
    global $_uploadRoot;
    if (!is_local_server()) {
        return '신규 이미지 등록은 로컬(사내 PC·서버)에서 실행할 때만 사용할 수 있습니다.';
    }
    if (empty($_uploadRoot)) {
        return '업로드 폴더($_uploadRoot)가 설정되지 않았습니다. db_config.php 를 확인해 주세요.';
    }
    if (!function_exists('imagecreatetruecolor')) {
        return '이 서버에 이미지 처리(GD) 확장이 없어 썸네일을 만들 수 없습니다. php 를 extension=gd2 로 실행해 주세요.';
    }
    if (!@is_dir(nas_local($_uploadRoot))) {
        return 'NAS 업로드 폴더에 접근할 수 없습니다: ' . $_uploadRoot;
    }
    return '';
}

// php.ini 크기 값(2M, 512K …)을 바이트로
function ini_bytes($v) {
    $v = trim((string)$v);
    $n = (float)$v;
    switch (strtoupper(substr($v, -1))) {
        case 'G': $n *= 1024;
        // no break
        case 'M': $n *= 1024;
        // no break
        case 'K': $n *= 1024;
    }
    return (int)$n;
}

// 한 파일 최대 크기 (한 요청에 한 장씩 올림)
function upload_max_bytes() {
    $max = min(ini_bytes(ini_get('upload_max_filesize')), ini_bytes(ini_get('post_max_size')) - 65536);
    return max(0, $max);
}

function upload_err_msg($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE: return '파일이 너무 큽니다.';
        case UPLOAD_ERR_PARTIAL:   return '파일이 끝까지 전송되지 않았습니다. 다시 시도해 주세요.';
        case UPLOAD_ERR_NO_FILE:   return '파일이 없습니다.';
        default:                   return '파일을 받는 중 서버 오류가 발생했습니다. (#' . (int)$code . ')';
    }
}

// 원본으로 썸네일(JPEG, 긴 변 320px) 생성
//   성공: array(data, w, h, tw, th) — w/h 는 회전 반영한 원본 크기, 실패: 오류 문자열
function make_thumb($src) {
    $info = @getimagesize($src);
    if (!$info) {
        return '이미지 파일이 아니거나 손상된 파일입니다.';
    }
    @ini_set('memory_limit', '1024M');  // 고해상도 사진은 GD 메모리를 많이 씀
    switch ($info[2]) {
        case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $im = @imagecreatefrompng($src); break;
        case IMAGETYPE_GIF:  $im = @imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $im = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
        case IMAGETYPE_BMP:  $im = function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($src) : false; break;
        default:             $im = false;
    }
    if (!$im) {
        return '썸네일을 만들 수 없는 이미지 형식입니다.';
    }

    // 휴대폰 사진: EXIF 방향대로 세움
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src);
        $deg = array(3 => 180, 6 => -90, 8 => 90);
        $o = isset($exif['Orientation']) ? (int)$exif['Orientation'] : 1;
        if (isset($deg[$o])) {
            $r = imagerotate($im, $deg[$o], 0);
            if ($r) {
                imagedestroy($im);
                $im = $r;
            }
        }
    }

    $w = imagesx($im);
    $h = imagesy($im);
    $scale = min(1, THUMB_MAX / max($w, $h));  // 작은 이미지는 키우지 않음
    $tw = max(1, (int)round($w * $scale));
    $th = max(1, (int)round($h * $scale));
    $t = imagecreatetruecolor($tw, $th);
    imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));  // 투명 배경은 흰색으로
    imagecopyresampled($t, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
    imagedestroy($im);

    ob_start();
    imagejpeg($t, null, 85);
    $data = ob_get_clean();
    imagedestroy($t);
    return array('data' => $data, 'w' => $w, 'h' => $h, 'tw' => $tw, 'th' => $th);
}

// DB 경로 형식 전체 경로의 path_hash (적재 프로그램과 동일: sha1(folder_path\file_name))
function path_hash($folder, $file) {
    return sha1(rtrim($folder, '\\/') . '\\' . $file, true);
}

// 사업 폴더에 같은 이름(파일 또는 DB 등록분)이 있으면 _1, _2 … 를 붙인 이름
function unique_name($folder, $base, $ext) {
    for ($i = 0; ; $i++) {
        $name = $base . ($i ? '_' . $i : '') . '.' . $ext;
        if (!file_exists(nas_path($folder, $name))
            && !q("SELECT 1 FROM NAS_IMG WHERE path_hash = ? AND db_state = 'Y' LIMIT 1", 's', array(path_hash($folder, $name)))) {
            return $name;
        }
    }
}
