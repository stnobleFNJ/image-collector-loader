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

    // 사업 선택 바 (render_bsbar): 칩 선택 / 사업명 검색 / 펼치기
    //   multi: 여러 사업 선택 허용, onChange: 사용자가 선택을 바꿀 때
    function initBsBar(multi, onChange) {
        var $ = function (id) { return document.getElementById(id); };
        var chipsEl = $('bsChips'), moreBtn = $('bsToggleMore');
        var chips = Array.from(chipsEl.querySelectorAll('.chip'));
        var selected = new Set();  // bs_id

        function render() {
            chips.forEach(function (c) { c.classList.toggle('on', selected.has(+c.dataset.id)); });
            $('bsSelCount').textContent = selected.size + '개 선택';
        }
        function changed() { render(); onChange(); }

        chipsEl.addEventListener('click', function (e) {
            var c = e.target.closest('.chip');
            if (!c) { return; }
            var id = +c.dataset.id;
            if (selected.has(id)) {
                selected.delete(id);
            } else {
                if (!multi) { selected.clear(); }
                selected.add(id);
            }
            changed();
        });

        $('bsClear').addEventListener('click', function () {
            if (!selected.size) { return; }
            selected.clear();
            changed();
        });

        // 접힘/펼침 상태를 버튼 문구·아이콘·숨은 개수로 표시
        function setExpanded(open) {
            chipsEl.classList.toggle('collapsed', !open);
            moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            moreBtn.querySelector('.toggle-label').textContent = open ? '접기' : '펼치기';
            updateMoreCount();
        }

        function updateMoreCount() {
            var cnt = $('bsMoreCnt');
            var hiddenN = 0;
            if (chipsEl.classList.contains('collapsed')) {
                var limit = chipsEl.getBoundingClientRect().top + chipsEl.clientHeight - 8;
                chips.forEach(function (c) {
                    if (!c.hidden && c.getBoundingClientRect().top > limit) { hiddenN++; }
                });
            }
            cnt.hidden = hiddenN === 0;
            cnt.textContent = '+' + hiddenN;
            // 숨은 사업이 없으면 펼칠 필요가 없음
            moreBtn.hidden = hiddenN === 0 && chipsEl.classList.contains('collapsed');
        }

        moreBtn.addEventListener('click', function () {
            setExpanded(chipsEl.classList.contains('collapsed'));
        });

        $('bsFilter').addEventListener('input', function () {
            var kw = this.value.trim().toLowerCase();
            chips.forEach(function (c) { c.hidden = kw !== '' && c.dataset.name.toLowerCase().indexOf(kw) < 0; });
            if (kw) { setExpanded(true); } else { updateMoreCount(); }
        });

        if (window.ResizeObserver) { new ResizeObserver(updateMoreCount).observe(chipsEl.parentNode); }
        window.addEventListener('resize', updateMoreCount);
        updateMoreCount();

        return {
            chips: chips,
            selected: selected,
            // 선택한 사업명 (bs_id 는 재적재 시 바뀌므로 저장은 이름으로)
            names: function () {
                return chips.filter(function (c) { return selected.has(+c.dataset.id); })
                    .map(function (c) { return c.dataset.name; });
            },
            // 저장해 둔 사업명으로 선택 복원 (onChange 는 부르지 않음)
            restore: function (names) {
                chips.forEach(function (c) {
                    if (names.indexOf(c.dataset.name) >= 0 && (multi || !selected.size)) { selected.add(+c.dataset.id); }
                });
                render();
            }
        };
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
        store: store, load: load, toast: toast, api: api, initSize: initSize, initBsBar: initBsBar,
        broadcast: broadcast, onBroadcast: onBroadcast,
        silent: function (e) { if (e.message !== 'login') { toast(e.message); } }
    };
})();
