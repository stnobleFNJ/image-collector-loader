<?php
// 내 선택만 보기 (새 창)
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

$member = require_member_page();
$bs = int_list(isset($_GET['bs']) ? $_GET['bs'] : '');
$bsNames = $bs ? q("SELECT bs_id, bs_name FROM NAS_BS WHERE bs_id IN (" . implode(',', $bs) . ") ORDER BY bs_name") : array();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php render_head('내 선택 · ' . APP_TITLE); ?>
</head>
<body class="app-body picks-body">

<header class="topbar">
    <div class="topbar-left">
        <span class="brand">
            <img src="assets/fnj_logo_icon.png" alt="FNJ">
            <span>내 선택 <span class="badge" id="pickCount">0</span></span>
        </span>
    </div>
    <label class="size-ctrl" title="썸네일 크기">
        <svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="3" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <input type="range" id="sizeRange" min="80" max="320" step="10" value="160" aria-label="썸네일 크기">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>
        <output id="sizeVal">160px</output>
    </label>
    <div class="user">
        <button type="button" class="btn btn-ghost btn-sm" onclick="window.close()">닫기</button>
    </div>
</header>

<div class="picks-bar">
    <div class="picks-scope">
        <button type="button" class="seg<?= $bs ? ' on' : '' ?>" id="scopeBs" <?= $bs ? '' : 'hidden' ?>>
            선택한 사업 <?= count($bsNames) ?>개
        </button>
        <button type="button" class="seg<?= $bs ? '' : ' on' ?>" id="scopeAll">전체보기</button>
        <span class="muted picks-names" id="scopeNames"><?= h(implode(', ', array_column($bsNames, 'bs_name'))) ?></span>
    </div>
    <div class="picks-actions">
        <span class="muted picks-hint">썸네일을 클릭하면 선택이 해제됩니다</span>
        <form method="post" action="download.php" id="dlForm" class="dl-inline">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="bs" id="dlBs" value="">
            <?php render_download_buttons('btn-sm'); ?>
        </form>
    </div>
</div>

<main class="picks-main">
    <div class="empty" id="pickEmpty" hidden>
        <img src="assets/fnj_logo_icon.png" alt="">
        <p>선택한 썸네일이 없습니다.</p>
    </div>
    <div class="grid" id="grid"></div>
</main>

<div class="toast" id="toast"></div>

<script>window.PICKS = { bs: <?= json_encode(implode(',', $bs)) ?> };</script>
<?php render_core_js(); ?>
<script src="assets/picks.js?v=<?= filemtime(__DIR__ . '/assets/picks.js') ?>"></script>
</body>
</html>
