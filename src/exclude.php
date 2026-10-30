<?php
// 이미지 제외 (총괄 어드민 전용): 썸네일을 누르면 제외(db_state=D) / 다시 누르면 복구
//   제외된 이미지는 다른 회원 화면에 나오지 않고, 여기서는 '삭제' 도장과 함께 보임
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

$member = require_admin_page();
$businesses = business_list();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php render_head('이미지 제외 · ' . APP_TITLE); ?>
</head>
<body class="app-body">

<?php render_topbar($member, 'exclude'); ?>

<?php render_bsbar($businesses); ?>

<div class="layout layout-single">
    <main class="main">
        <div class="main-head">
            <h2 id="gridTitle">썸네일</h2>
            <span class="muted" id="gridInfo"></span>
            <div class="x-filter" role="group" aria-label="보기">
                <button type="button" class="seg on" id="fAll">전체</button>
                <button type="button" class="seg" id="fOnly">제외된 이미지만</button>
            </div>
        </div>
        <p class="muted x-hint">썸네일을 누르면 <b>제외</b>되어 다른 회원에게 보이지 않습니다. <span class="x-stamp-sample">삭제</span> 도장이 찍힌 이미지를 다시 누르면 복구됩니다.</p>
        <div class="empty" id="gridEmpty">
            <img src="assets/fnj_logo_icon.png" alt="">
            <p>위에서 사업을 선택하면 썸네일이 표시됩니다.<br><small>여러 사업을 함께 선택할 수 있습니다.</small></p>
        </div>
        <div class="grid xgrid" id="grid"></div>
        <div class="sentinel" id="sentinel"><span class="spinner"></span></div>
    </main>
</div>

<div class="toast" id="toast"></div>

<?php render_core_js(); ?>
<script src="assets/exclude.js?v=<?= filemtime(__DIR__ . '/assets/exclude.js') ?>"></script>
</body>
</html>
