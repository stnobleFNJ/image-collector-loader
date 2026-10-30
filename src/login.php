<?php
define('APP_INIT', true);
require __DIR__ . '/inc/common.php';

if (current_member()) {
    header('Location: check.php');
    exit;
}

$error = '';
$loginId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginId = trim(isset($_POST['login_id']) ? (string)$_POST['login_id'] : '');
    $passwd  = isset($_POST['passwd']) ? (string)$_POST['passwd'] : '';

    if (!csrf_ok()) {
        $error = '페이지가 만료되었습니다. 다시 시도해 주세요.';
    } elseif ($loginId === '' || $passwd === '') {
        $error = '아이디와 비밀번호를 입력해 주세요.';
    } else {
        $rows = q("SELECT mem_id, passwd FROM NAS_MEMBER WHERE login_id = ? AND db_state = 'Y'", 's', array($loginId));
        if ($rows && password_verify($passwd, $rows[0]['passwd'])) {
            session_regenerate_id(true);
            $_SESSION['mem_id'] = (int)$rows[0]['mem_id'];
            q("UPDATE NAS_MEMBER SET last_login_at = NOW() WHERE mem_id = ?", 'i', array($rows[0]['mem_id']));
            header('Location: check.php');
            exit;
        }
        usleep(500000);
        $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php render_head(APP_TITLE . ' · 로그인'); ?>
</head>
<body class="login-body">

<main class="login-card">
    <img class="login-logo" src="assets/fnj_logo_icon.png" alt="FNJ">
    <h1 class="login-title">FNJ 이미지 <span>Check</span></h1>
    <p class="login-sub">사업별 이미지를 확인하고 선택하세요</p>

    <?php if ($error !== ''): ?>
        <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="login-form" autocomplete="on">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <label class="field">
            <span>아이디</span>
            <input type="text" name="login_id" value="<?= h($loginId) ?>" required autofocus autocomplete="username">
        </label>
        <label class="field">
            <span>비밀번호</span>
            <input type="password" name="passwd" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn-primary btn-block">로그인</button>
    </form>

    <button type="button" class="link-btn" id="openSignup">회원가입 요청</button>
</main>

<dialog id="signupDlg" class="dlg">
    <form id="signupForm" class="dlg-body">
        <h2>회원가입 요청</h2>
        <p class="muted">관리자에게 요청 메일이 발송됩니다. 승인되면 계정을 안내해 드립니다.</p>
        <label class="field"><span>이름</span><input type="text" name="name" maxlength="50" required></label>
        <label class="field"><span>희망 아이디</span><input type="text" name="want_id" maxlength="50" required></label>
        <label class="field"><span>이메일</span><input type="email" name="email" maxlength="100" required></label>
        <label class="field"><span>코멘트</span><textarea name="comment" rows="4" maxlength="2000" placeholder="소속, 사용 목적 등을 적어 주세요" required></textarea></label>
        <div class="dlg-msg" id="signupMsg"></div>
        <div class="dlg-actions">
            <button type="button" class="btn btn-ghost" id="closeSignup">닫기</button>
            <button type="submit" class="btn btn-primary">요청 보내기</button>
        </div>
    </form>
</dialog>

<script>
(function () {
    var dlg = document.getElementById('signupDlg');
    var form = document.getElementById('signupForm');
    var msg = document.getElementById('signupMsg');
    document.getElementById('openSignup').onclick = function () { msg.textContent = ''; dlg.showModal(); };
    document.getElementById('closeSignup').onclick = function () { dlg.close(); };
    form.onsubmit = function (e) {
        e.preventDefault();
        var btn = form.querySelector('[type=submit]');
        btn.disabled = true;
        msg.className = 'dlg-msg';
        msg.textContent = '보내는 중…';
        var fd = new FormData(form);
        fetch('signup_request.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': <?= json_encode(csrf_token()) ?> },
            body: fd
        }).then(function (r) { return r.json(); }).then(function (d) {
            msg.className = 'dlg-msg ' + (d.ok ? 'ok' : 'err');
            msg.textContent = d.ok ? '요청을 보냈습니다. 감사합니다.' : d.error;
            if (d.ok) { form.reset(); setTimeout(function () { dlg.close(); }, 1500); }
        }).catch(function () {
            msg.className = 'dlg-msg err';
            msg.textContent = '전송에 실패했습니다. 잠시 후 다시 시도해 주세요.';
        }).then(function () { btn.disabled = false; });
    };
})();
</script>
</body>
</html>
