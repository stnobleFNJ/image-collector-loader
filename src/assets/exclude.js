// 이미지 제외 (총괄 어드민): 썸네일 클릭 → 제외(D) / 삭제 도장 클릭 → 복구(Y)
(function () {
    'use strict';

    var $ = function (id) { return document.getElementById(id); };
    var grid = $('grid'), sentinel = $('sentinel');
    var LS_BS = 'fnjimg.bs';  // 이미지 확인 탭과 사업 선택 공유
    var toast = FNJ.toast, api = FNJ.api;

    var bar = FNJ.initBsBar(true, onBsChanged);

    var state = {
        bs: bar.selected,
        only: false,       // 제외된 이미지만 보기
        offset: 0,
        more: false,
        loading: false,
        reqSeq: 0,         // 사업/보기 변경 시 이전 응답 무시용
        total: 0,
        excluded: 0
    };

    function bsParam() { return Array.from(state.bs).join(','); }

    FNJ.initSize(function () { if (state.more && !state.loading && isNearBottom()) { loadGrid(false); } });

    /* ---------- 사업 / 보기 ---------- */
    function onBsChanged() {
        var names = bar.names();
        $('gridTitle').textContent = names.length ? names.slice(0, 3).join(', ') + (names.length > 3 ? ' 외 ' + (names.length - 3) + '개' : '') : '썸네일';
        FNJ.store(LS_BS, JSON.stringify(names));
        loadGrid(true);
    }

    function setOnly(only) {
        if (state.only === only) { return; }
        state.only = only;
        $('fAll').classList.toggle('on', !only);
        $('fOnly').classList.toggle('on', only);
        loadGrid(true);
    }
    $('fAll').addEventListener('click', function () { setOnly(false); });
    $('fOnly').addEventListener('click', function () { setOnly(true); });

    /* ---------- 썸네일 그리드 ---------- */
    function makeTile(it) {
        var el = document.createElement('div');
        el.className = 'tile' + (it.off ? ' excluded' : '');
        el.dataset.id = it.id;
        el.dataset.bs = it.bsid;
        el.title = it.name + (it.bs ? '\n' + it.bs + (it.yr ? ' · ' + it.yr : '') : '');
        var img = document.createElement('img');
        img.loading = 'lazy';
        img.decoding = 'async';
        img.alt = '';
        img.src = 'thumb.php?id=' + it.id;
        el.appendChild(img);
        el.insertAdjacentHTML('beforeend', '<span class="stamp" aria-hidden="true">삭제</span><span class="x-act"></span><span class="tile-name"></span>');
        el.querySelector('.tile-name').textContent = it.name;
        return el;
    }

    function renderInfo() {
        $('gridInfo').textContent = state.offset.toLocaleString() + ' / ' + state.total.toLocaleString() + '장'
            + (state.only ? '' : ' · 제외 ' + state.excluded.toLocaleString() + '장');
    }

    function loadGrid(reset) {
        if (reset) {
            state.reqSeq++;
            state.offset = 0;
            state.more = false;
            state.loading = false;
            grid.innerHTML = '';
            $('gridInfo').textContent = '';
        }
        $('gridEmpty').hidden = state.bs.size > 0;
        if (!state.bs.size || state.loading) { sentinel.classList.remove('on'); return; }

        var seq = state.reqSeq;
        state.loading = true;
        sentinel.classList.add('on');
        api({ act: 'xitems', bs: bsParam(), offset: state.offset, only: state.only ? 1 : 0 }).then(function (d) {
            if (seq !== state.reqSeq) { return; }
            var frag = document.createDocumentFragment();
            d.items.forEach(function (it) { frag.appendChild(makeTile(it)); });
            grid.appendChild(frag);
            state.offset += d.items.length;
            state.more = d.more;
            if (d.total !== null) { state.total = d.total; state.excluded = d.excluded; }
            renderInfo();
        }).catch(FNJ.silent).then(function () {
            if (seq !== state.reqSeq) { return; }
            state.loading = false;
            sentinel.classList.toggle('on', state.more);
            if (state.more && isNearBottom()) { loadGrid(false); }
        });
    }

    function isNearBottom() {
        return sentinel.getBoundingClientRect().top < window.innerHeight + 600;
    }

    new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting && state.more && !state.loading) { loadGrid(false); }
    }, { rootMargin: '600px 0px' }).observe(sentinel);

    /* ---------- 제외 / 복구 ---------- */
    // 사업 칩의 이미지 수(표시 중 기준)도 함께 맞춤
    function bumpChip(bsid, delta) {
        var c = bar.chips.filter(function (x) { return +x.dataset.id === bsid; })[0];
        var cnt = c && c.querySelector('.chip-cnt');
        if (cnt) { cnt.textContent = Math.max(0, parseInt(cnt.textContent.replace(/,/g, ''), 10) + delta).toLocaleString(); }
    }

    function setExcluded(tile, on) {
        tile.classList.toggle('excluded', on);
        state.excluded += on ? 1 : -1;
        bumpChip(+tile.dataset.bs, on ? -1 : 1);
        renderInfo();
    }

    grid.addEventListener('click', function (e) {
        var tile = e.target.closest('.tile');
        if (!tile || tile.classList.contains('busy')) { return; }
        var on = !tile.classList.contains('excluded');
        tile.classList.add('busy');
        setExcluded(tile, on);  // 먼저 화면에 반영
        api({ act: 'exclude', id: tile.dataset.id, on: on ? 1 : 0 }, true).then(function () {
            toast(on ? '제외했습니다. 다시 누르면 복구됩니다.' : '복구했습니다.');
        }).catch(function (e) {
            setExcluded(tile, !on);
            if (e.message !== 'login') { toast('저장 실패: ' + e.message); }
        }).then(function () { tile.classList.remove('busy'); });
    });

    /* ---------- 시작 ---------- */
    var saved = [];
    try { saved = JSON.parse(FNJ.load(LS_BS) || '[]'); } catch (e) {}
    bar.restore(saved);
    onBsChanged();
})();
