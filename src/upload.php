<?php
// 신규 이미지 등록 (로컬 서버 전용): 사업 선택 → 원본 여러 장 업로드 → NAS 저장 + 썸네일 DB 등록
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';
require __DIR__ . '/inc/upload.php';

$member = require_member_page();
if (!is_local_server()) {
    header('Location: check.php');
    exit;
}
$block = upload_block_reason();
$businesses = business_list();
$maxBytes = upload_max_bytes();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php render_head('신규 이미지 등록 · ' . APP_TITLE); ?>
</head>
<body class="app-body">

<?php render_topbar($member, 'upload', false); ?>

<?php render_bsbar($businesses); ?>

<div class="up-wrap">
    <?php if ($block !== ''): ?>
        <div class="flash flash-err"><?= h($block) ?></div>
    <?php endif; ?>

    <section class="card">
        <div class="card-head">
            <h2>원본 이미지 <small id="upCount">0장</small></h2>
            <p class="muted card-sub" id="upTarget">위에서 이미지를 넣을 사업을 하나 선택해 주세요.</p>
        </div>

        <label class="dropzone" id="dropzone">
            <input type="file" id="fileInput" multiple accept="<?= h('.' . implode(',.', UPLOAD_EXTS)) ?>"<?= $block !== '' ? ' disabled' : '' ?>>
            <svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <b>이미지를 여기로 끌어다 놓거나 클릭해서 선택하세요</b>
            <small><?= h(strtoupper(implode(' · ', UPLOAD_EXTS))) ?> · 파일당 최대 <?= number_format($maxBytes / 1048576) ?>MB · 여러 장을 한 번에 올릴 수 있습니다</small>
            <span class="btn btn-ghost btn-sm">파일 선택</span>
        </label>

        <div class="qlist" id="qList"></div>

        <div class="up-actions">
            <div class="up-progress" id="upProgress" hidden>
                <div class="up-bar"><i id="upBar"></i></div>
                <span id="upProgText"></span>
            </div>
            <button type="button" class="btn btn-ghost" id="qClear" disabled>목록 비우기</button>
            <button type="button" class="btn btn-primary" id="upStart">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 20h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                원본 업로드
            </button>
        </div>
    </section>

    <section class="card" id="resultCard" hidden>
        <div class="card-head up-result-head">
            <div>
                <h2>업로드 결과 <small id="resultInfo"></small></h2>
                <p class="muted card-sub">DB 에 저장된 썸네일입니다. 저장 위치: <span class="mono" id="resultPath"></span></p>
            </div>
            <a class="btn btn-ghost btn-sm" id="goCheck" href="check.php">이미지 확인에서 보기</a>
        </div>
        <div class="grid view-only" id="resultGrid"></div>
    </section>
</div>

<div class="toast" id="toast"></div>

<?php render_core_js(); ?>
<script>window.UPLOAD = <?= json_encode(array(
    'enabled'  => $block === '',
    'maxBytes' => $maxBytes,
    'exts'     => UPLOAD_EXTS,
    'root'     => isset($_uploadRoot) ? $_uploadRoot : '',
), JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="assets/upload.js?v=<?= filemtime(__DIR__ . '/assets/upload.js') ?>"></script>
</body>
</html>
