(function () {
    'use strict';

    var $ = function (id) { return document.getElementById(id); };
    var grid = $('grid');
    var state = { all: !PICKS.bs, items: new Map(), seq: 0 };

    FNJ.initSize();

    function scopeBs() { return state.all ? '' : PICKS.bs; }

    function render() {
        var n = state.items.size;
        $('pickCount').textContent = n.toLocaleString();
        $('pickEmpty').hidden = n > 0;
        document.querySelectorAll('.dl-btn').forEach(function (b) { b.disabled = n === 0; });
        $('dlBs').value = scopeBs();
        $('scopeBs').classList.toggle('on', !state.all);
        $('scopeAll').classList.toggle('on', state.all);
        $('scopeNames').hidden = state.all;
    }

    function makeTile(it) {
        var el = document.createElement('div');
        el.className = 'tile picked unpick';
        el.dataset.id = it.id;
        el.title = it.name + '\n' + it.bs + (it.yr ? ' · ' + it.yr : '') + '\n클릭하면 선택 해제';
        el.innerHTML =
            '<img loading="lazy" decoding="async" alt="">' +
            '<span class="check" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span>' +
            '<span class="unpick-hint">선택 해제</span>' +
            '<span class="tile-name"></span>';
        el.querySelector('img').src = 'thumb.php?id=' + it.id;
        el.querySelector('.tile-name').textContent = it.name;
        return el;
    }

    function load() {
        var seq = ++state.seq;
        FNJ.api({ act: 'picks', bs: scopeBs() }).then(function (d) {
            if (seq !== state.seq) { return; }
            state.items.clear();
            grid.innerHTML = '';
            var frag = document.createDocumentFragment();
            d.items.forEach(function (it) { state.items.set(it.id, it); frag.appendChild(makeTile(it)); });
            grid.appendChild(frag);
            render();
        }).catch(FNJ.silent);
    }

    function removeTile(id) {
        state.items.delete(id);
        var t = grid.querySelector('.tile[data-id="' + id + '"]');
        if (t) {
            t.classList.add('leaving');
            setTimeout(function () { t.remove(); }, 200);
        }
        render();
    }

    grid.addEventListener('click', function (e) {
        var t = e.target.closest('.tile');
        if (!t || t.classList.contains('leaving')) { return; }
        var it = state.items.get(+t.dataset.id);
        if (!it) { return; }
        removeTile(it.id);  // 먼저 화면에 반영
        FNJ.api({ act: 'toggle', id: it.id, on: 0 }, true).then(function () {
            FNJ.broadcast({ type: 'pick', item: it, on: false });
            FNJ.toast('선택을 해제했습니다.');
        }).catch(function (e) {
            FNJ.silent(e);
            load();
        });
    });

    $('dlForm').addEventListener('submit', function (e) {
        var orig = e.submitter && e.submitter.value === 'orig';
        FNJ.toast(orig ? 'NAS 원본을 모으는 중입니다. 장수가 많으면 시간이 걸립니다…' : 'ZIP 파일을 만드는 중입니다…');
    });

    $('scopeAll').addEventListener('click', function () { if (!state.all) { state.all = true; load(); } });
    $('scopeBs').addEventListener('click', function () { if (state.all && PICKS.bs) { state.all = false; load(); } });

    // 메인 창에서 선택/해제하면 실시간 반영
    FNJ.onBroadcast(function (msg) {
        if (msg.type === 'pick' && !msg.on) { removeTile(msg.item.id); } else { load(); }
    });

    load();
})();
