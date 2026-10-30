// 공통: 저장소, 토스트, API, 썸네일 크기, 창 간 선택 동기화
(function () {
    'use strict';

    var LS_SIZE = 'fnjimg.size';

    function store(key, val) { try { localStorage.setItem(key, val); } catch (e) {} }
    function load(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }

    function toast(msg) {
        var t = document.getElementById('toast');
        if (!t) { return; }
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(function () { t.classList.remove('show'); }, 2200);
    }

    function api(params, post) {
        var opt = { headers: { 'X-CSRF-Token': APP.csrf }, credentials: 'same-origin' };
        var url = 'api.php';
        if (post) {
            opt.method = 'POST';
            opt.body = new URLSearchParams(params);
        } else {
            url += '?' + new URLSearchParams(params).toString();
        }
        return fetch(url, opt).then(function (r) {
            return r.json().catch(function () { return { ok: false, error: '서버 응답 오류' }; }).then(function (d) {
                if (d.login) {
                    alert(d.error || '불법적인 접속입니다.');
                    location.replace('login.php');
                    throw new Error('login');
                }
                if (!d.ok) { throw new Error(d.error || '요청 실패'); }
                return d;
            });
        });
    }

    // 상단 썸네일 크기 조절 (localStorage 로 창 간 공유)
    function initSize(onChange) {
        var range = document.getElementById('sizeRange');
        if (!range) { return null; }
        var apply = function (v) {
            document.documentElement.style.setProperty('--thumb', v + 'px');
            document.getElementById('sizeVal').textContent = v + 'px';
        };
        range.value = load(LS_SIZE) || range.value;
        apply(range.value);
        range.addEventListener('input', function () { apply(range.value); store(LS_SIZE, range.value); });
        if (onChange) { range.addEventListener('change', onChange); }
        return range;
    }

    // 사내망 밖에서 원본 다운로드를 누르면 안내
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-blocked]');
        if (b) { e.preventDefault(); alert(b.dataset.blocked); }
    });

    // 다른 창(메인 ↔ 내 선택 창)에 선택 변경 알림
    var channel = window.BroadcastChannel ? new BroadcastChannel('fnjimg-picks') : null;
    function broadcast(msg) { if (channel) { channel.postMessage(msg); } }
    function onBroadcast(fn) { if (channel) { channel.onmessage = function (e) { fn(e.data); }; } }

    window.FNJ = {
        store: store, load: load, toast: toast, api: api, initSize: initSize,
        broadcast: broadcast, onBroadcast: onBroadcast,
        silent: function (e) { if (e.message !== 'login') { toast(e.message); } }
    };
})();
