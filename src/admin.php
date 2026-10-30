<?php
// 총괄 어드민: 계정 목록 / 신규 등록 / 활성·비활성
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

$member = require_admin_page();

function flash($type, $msg) {
    $_SESSION['flash'] = array($type, $msg);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if (!csrf_ok()) {
        flash('err', '페이지가 만료되었습니다. 다시 시도해 주세요.');
    } elseif ($act === 'create') {
        $name    = trim(isset($_POST['name']) ? (string)$_POST['name'] : '');
        $loginId = trim(isset($_POST['login_id']) ? (string)$_POST['login_id'] : '');
        $passwd  = isset($_POST['passwd']) ? (string)$_POST['passwd'] : '';
        $exists  = $loginId !== '' ? q("SELECT mem_id FROM NAS_MEMBER WHERE login_id = ?", 's', array($loginId)) : array();

        if ($name === '' || $loginId === '' || $passwd === '') {
            flash('err', '이름, 아이디, 비밀번호를 모두 입력해 주세요.');
        } elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $loginId)) {
            flash('err', '아이디는 영문, 숫자, _ . - 조합 3~50자로 입력해 주세요.');
        } elseif (mb_strlen($name) > 50) {
            flash('err', '이름은 50자 이내로 입력해 주세요.');
        } elseif (strlen($passwd) < 4) {
            flash('err', '비밀번호는 4자 이상 입력해 주세요.');
        } elseif ($exists) {
            flash('err', "이미 사용 중인 아이디입니다: {$loginId}");
        } else {
            q("INSERT INTO NAS_MEMBER (login_id, passwd, name, created_at, updated_at, db_state)
               VALUES (?, ?, ?, NOW(), NOW(), 'Y')",
                'sss', array($loginId, password_hash($passwd, PASSWORD_DEFAULT), $name));
            flash('ok', "계정을 등록했습니다: {$name} ({$loginId})");
        }
    } elseif ($act === 'state') {
        $memId = isset($_POST['mem_id']) ? (int)$_POST['mem_id'] : 0;
        $state = (isset($_POST['state']) && $_POST['state'] === 'Y') ? 'Y' : 'N';
        if ($memId === (int)$member['mem_id']) {
            flash('err', '본인 계정은 비활성화할 수 없습니다.');
        } else {
            q("UPDATE NAS_MEMBER SET db_state = ?, updated_at = NOW() WHERE mem_id = ? AND db_state IN ('Y', 'N')",
                'si', array($state, $memId));
            flash('ok', $state === 'Y' ? '계정을 활성화했습니다.' : '계정을 비활성화했습니다.');
        }
    }
    header('Location: admin.php');
    exit;
}

$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);

$members = q("SELECT m.mem_id, m.login_id, m.name, m.is_admin, m.db_state, m.last_login_at, m.created_at,
                     (SELECT COUNT(*) FROM NAS_MEMBER_PICK p WHERE p.mem_id = m.mem_id) AS picks
              FROM NAS_MEMBER m
              WHERE m.db_state IN ('Y', 'N')
              ORDER BY m.db_state = 'Y' DESC, m.is_admin DESC, m.created_at DESC");
$activeCnt = count(array_filter($members, function ($m) { return $m['db_state'] === 'Y'; }));
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php render_head('계정 관리 · ' . APP_TITLE); ?>
</head>
<body class="app-body">

<?php render_topbar($member, 'admin', false); ?>

<div class="admin-wrap">
    <?php if ($flash): ?>
        <div class="flash <?= $flash[0] === 'ok' ? 'flash-ok' : 'flash-err' ?>"><?= h($flash[1]) ?></div>
    <?php endif; ?>

    <section class="card admin-new">
        <h2>신규 계정 등록</h2>
        <p class="muted">등록한 아이디와 비밀번호로 바로 로그인할 수 있습니다.</p>
        <form method="post" class="new-form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="act" value="create">
            <label class="field"><span>이름</span><input type="text" name="name" maxlength="50" required></label>
            <label class="field"><span>아이디</span><input type="text" name="login_id" maxlength="50" pattern="[A-Za-z0-9_.\-]{3,50}" title="영문, 숫자, _ . - 조합 3~50자" required autocomplete="off"></label>
            <label class="field"><span>비밀번호</span><input type="password" name="passwd" minlength="4" required autocomplete="new-password"></label>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                계정 추가
            </button>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>등록된 계정 <small><?= $activeCnt ?>명 사용 중 · 전체 <?= count($members) ?>명</small></h2>
            <p class="muted card-sub">계정을 클릭하면 그 계정이 선택한 이미지를 새 창에서 보고 다운로드할 수 있습니다.</p>
        </div>
        <div class="table-wrap">
            <table class="mtable">
                <thead>
                    <tr>
                        <th>이름</th><th>아이디</th><th>상태</th><th class="num">선택 수</th>
                        <th>최근 로그인</th><th>등록일</th><th class="act">관리</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $m): $isMe = (int)$m['mem_id'] === (int)$member['mem_id']; ?>
                    <tr class="mrow <?= $m['db_state'] === 'Y' ? '' : 'off' ?>" data-href="member_picks.php?m=<?= (int)$m['mem_id'] ?>">
                        <td>
                            <a class="mlink" href="member_picks.php?m=<?= (int)$m['mem_id'] ?>" target="_blank" title="선택한 이미지 보기 (새 창)">
                                <?= h($m['name']) ?>
                                <svg viewBox="0 0 24 24" width="13" height="13" aria-hidden="true"><path d="M14 4h6v6M20 4l-8 8M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <?php if ($m['is_admin']): ?><em class="role">관리자</em><?php endif; ?>
                        </td>
                        <td class="mono"><?= h($m['login_id']) ?></td>
                        <td>
                            <span class="state <?= $m['db_state'] === 'Y' ? 'on' : 'off' ?>">
                                <?= $m['db_state'] === 'Y' ? '활성' : '비활성' ?>
                            </span>
                        </td>
                        <td class="num"><?= number_format($m['picks']) ?></td>
                        <td class="muted"><?= $m['last_login_at'] ? h(substr($m['last_login_at'], 0, 16)) : '-' ?></td>
                        <td class="muted"><?= h(substr($m['created_at'], 0, 10)) ?></td>
                        <td class="act">
                            <?php if ($isMe): ?>
                                <span class="muted">본인</span>
                            <?php else: ?>
                                <form method="post" class="inline"
                                      data-confirm="<?= h($m['name'] . '(' . $m['login_id'] . ') 계정을 ' . ($m['db_state'] === 'Y' ? '비활성화' : '활성화') . '할까요?') ?>">
                                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="act" value="state">
                                    <input type="hidden" name="mem_id" value="<?= (int)$m['mem_id'] ?>">
                                    <?php if ($m['db_state'] === 'Y'): ?>
                                        <input type="hidden" name="state" value="N">
                                        <button type="submit" class="btn btn-ghost btn-sm btn-danger">비활성화</button>
                                    <?php else: ?>
                                        <input type="hidden" name="state" value="Y">
                                        <button type="submit" class="btn btn-ghost btn-sm">활성화</button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>
// 계정 행 클릭 → 선택 이미지 새 창 (전체 창)
document.querySelectorAll('tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
        if (e.target.closest('a, button, form')) { return; }
        var w = window.open(tr.dataset.href, '_blank');
        if (w) { w.focus(); } else { location.href = tr.dataset.href; }
    });
});
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
        if (!confirm(f.dataset.confirm)) { e.preventDefault(); }
    });
});
</script>
</body>
</html>
