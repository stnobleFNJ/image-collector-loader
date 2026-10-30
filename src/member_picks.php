<?php
// (총괄 어드민) 계정별 선택 이미지 보기 / 다운로드 (새 창)
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

$member = require_admin_page();
$memId = isset($_GET['m']) ? (int)$_GET['m'] : 0;
$rows = q("SELECT mem_id, login_id, name, is_admin, db_state FROM NAS_MEMBER WHERE mem_id = ? AND db_state IN ('Y', 'N')",
    'i', array($memId));
if (!$rows) {
    echo '<!DOCTYPE html><meta charset="utf-8"><script>alert("계정을 찾을 수 없습니다.");window.close();location.replace("admin.php");</script>';
    exit;
}
$target = $rows[0];
$targetName = $target['name'] !== '' ? $target['name'] : $target['login_id'];

// 이 계정이 선택한 이미지의 사업별 장수 (현재 적재분)
$businesses = q("SELECT i.bs_id, i.bs_name, COUNT(*) AS cnt
                 FROM NAS_MEMBER_PICK p
                 JOIN NAS_IMG i ON i.path_hash = p.path_hash AND i.db_state = 'Y'
                 WHERE p.mem_id = ?
                 GROUP BY i.bs_id, i.bs_name
                 ORDER BY cnt DESC, i.bs_name",
    'i', array($memId));
$total = array_sum(array_column($businesses, 'cnt'));
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php render_head($targetName . ' 선택 · ' . APP_TITLE); ?>
</head>
<body class="app-body">

<header class="topbar">
    <div class="topbar-left">
        <a class="brand" href="admin.php">
            <img src="assets/fnj_logo_icon.png" alt="FNJ">
            <span>FNJ 이미지 <b>Check</b></span>
        </a>
        <span class="mp-crumb">계정 관리 › 선택 이미지</span>
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

<div class="bsbar-wrap" id="bsbarWrap">
<section class="bsbar">
    <div class="mp-owner">
        <span class="mp-avatar" aria-hidden="true"><?= h(mb_substr($targetName, 0, 1)) ?></span>
        <div class="mp-who">
            <h1>
                <?= h($targetName) ?><span class="mp-id"><?= h($target['login_id']) ?></span>
                <?php if ($target['is_admin']): ?><em class="role">관리자</em><?php endif; ?>
                <?php if ($target['db_state'] !== 'Y'): ?><span class="state off">비활성</span><?php endif; ?>
            </h1>
            <p class="muted">선택한 이미지 <b><?= number_format($total) ?></b>장 · 사업 <?= count($businesses) ?>개</p>
        </div>
        <form method="post" action="download.php" id="dlForm" class="dl-inline mp-dl">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="mem" value="<?= (int)$target['mem_id'] ?>">
            <input type="hidden" name="bs" id="dlBs" value="">
            <?php render_download_buttons(); ?>
        </form>
    </div>

    <div class="bsbar-head">
        <h2>사업 <small id="bsSelCount"></small></h2>
        <input type="search" id="bsFilter" class="bs-filter" placeholder="사업명 검색">
        <button type="button" class="btn btn-ghost btn-sm" id="bsAll">전체선택</button>
        <button type="button" class="btn btn-ghost btn-sm" id="bsClear">선택 해제</button>
        <button type="button" class="btn btn-ghost btn-sm toggle-more" id="bsToggleMore" aria-expanded="false" aria-controls="bsChips">
            <span class="toggle-label">펼치기</span><span class="more-cnt" id="bsMoreCnt" hidden></span>
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
    </div>
    <div class="chips collapsed" id="bsChips">
        <?php foreach ($businesses as $b): ?>
            <button type="button" class="chip on" data-id="<?= (int)$b['bs_id'] ?>" data-name="<?= h($b['bs_name']) ?>">
                <?= h($b['bs_name']) ?><span class="chip-cnt"><?= number_format($b['cnt']) ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</section>
</div>

<main class="picks-main">
    <div class="main-head">
        <h2 id="gridTitle">전체 사업</h2>
        <span class="muted" id="gridInfo"></span>
    </div>
    <div class="empty" id="gridEmpty" hidden>
        <img src="assets/fnj_logo_icon.png" alt="">
        <p id="gridEmptyMsg"></p>
    </div>
    <div class="grid view-only" id="grid"></div>
</main>

<div class="toast" id="toast"></div>

<script>window.MP = { mem: <?= (int)$target['mem_id'] ?>, self: <?= (int)$target['mem_id'] === (int)$member['mem_id'] ? 'true' : 'false' ?> };</script>
<?php render_core_js(); ?>
<script src="assets/member_picks.js?v=<?= filemtime(__DIR__ . '/assets/member_picks.js') ?>"></script>
</body>
</html>
