<?php
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

$member = require_member_page();
$businesses = business_list();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php render_head(APP_TITLE); ?>
</head>
<body class="app-body">

<?php render_topbar($member, 'check'); ?>

<?php render_bsbar($businesses); ?>

<div class="layout">
    <main class="main">
        <div class="main-head">
            <h2 id="gridTitle">썸네일</h2>
            <span class="muted" id="gridInfo"></span>
        </div>
        <div class="empty" id="gridEmpty">
            <img src="assets/fnj_logo_icon.png" alt="">
            <p>위에서 사업을 선택하면 썸네일이 표시됩니다.<br><small>여러 사업을 함께 선택할 수 있습니다.</small></p>
        </div>
        <div class="grid" id="grid"></div>
        <div class="sentinel" id="sentinel"><span class="spinner"></span></div>
    </main>

    <aside class="side">
        <div class="side-card">
            <div class="side-head">
                <button type="button" class="side-title" id="openPicks" title="내 선택만 새 창에서 크게 보기">
                    내 선택 <span class="badge" id="pickCount">0</span>
                    <svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M14 4h6v6M20 4l-8 8M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <span class="muted side-scope" id="pickScope">전체 사업</span>
            </div>
            <div class="side-grid" id="pickGrid"></div>
            <div class="side-empty" id="pickEmpty">썸네일을 클릭하면 여기에 모입니다.</div>
            <form method="post" action="download.php" class="side-actions" id="dlForm">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="bs" id="dlBs" value="">
                <?php render_download_buttons(); ?>
                <button type="button" class="btn btn-ghost" id="pickClear" disabled>선택 비우기</button>
            </form>
        </div>
    </aside>
</div>

<div class="toast" id="toast"></div>

<?php render_core_js(); ?>
<script src="assets/check.js?v=<?= filemtime(__DIR__ . '/assets/check.js') ?>"></script>
</body>
</html>
