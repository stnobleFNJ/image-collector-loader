// (총괄 어드민) 계정별 선택 이미지: 사업 다중선택 필터 + 다운로드 (조회 전용)
(function () {
    'use strict';

    var $ = function (id) { return document.getElementById(id); };
    var chipsEl = $('bsChips'), grid = $('grid');
    var chips = Array.from(chipsEl.querySelectorAll('.chip'));

    var state = {
        bs: new Set(chips.map(function (c) { return +c.dataset.id; })),  // 처음엔 전체 사업 선택
        items: [],      // 이 계정의 선택 전체 (사업 필터는 화면에서 적용)
        shown: 0
    };

    FNJ.initSize();

    function allSelected() { return chips.length > 0 && state.bs.size === chips.length; }
    // 전체 선택이면 필터 없이 (다운로드도 전체)
    function bsParam() { return allSelected() ? '' : Array.from(state.bs).join(','); }

    /* ---------- 사업 선택 ---------- */
    function syncBsUi() {
        chips.forEach(function (c) { c.classList.toggle('on', state.bs.has(+c.dataset.id)); });
        $('bsSelCount').textContent = allSelected() ? '전체 ' + chips.length + '개' : state.bs.size + ' / ' + chips.length + '개 선택';
        $('bsAll').classList.toggle('on', allSelected());
        var names = chips.filter(function (c) { return state.bs.has(+c.dataset.id); })
            .map(function (c) { return c.dataset.name; });
        $('gridTitle').textContent = allSelected() ? '전체 사업'
            : names.length ? names.slice(0, 3).join(', ') + (names.length > 3 ? ' 외 ' + (names.length - 3) + '개' : '') : '사업 미선택';
        $('dlBs').value = bsParam();
    }

    function onBsChanged() {
        syncBsUi();
        renderGrid();
    }

    chipsEl.addEventListener('click', function (e) {
        var c = e.target.closest('.chip');
        if (!c) { return; }
        var id = +c.dataset.id;
        if (state.bs.has(id)) { state.bs.delete(id); } else { state.bs.add(id); }
        onBsChanged();
    });

    $('bsAll').addEventListener('click', function () {
        if (allSelected()) { return; }
        chips.forEach(function (c) { state.bs.add(+c.dataset.id); });
        onBsChanged();
    });

    $('bsClear').addEventListener('click', function () {
        if (!state.bs.size) { return; }
        state.bs.clear();
        onBsChanged();
    });

    var moreBtn = $('bsToggleMore');

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

    var topbar = document.querySelector('.topbar'), bsbarWrap = $('bsbarWrap');
    function updateStickyOffsets() {
        document.documentElement.style.setProperty('--topbar-h', topbar.offsetHeight + 'px');
    }
    if (window.ResizeObserver) {
        new ResizeObserver(function () { updateStickyOffsets(); updateMoreCount(); }).observe(bsbarWrap);
        new ResizeObserver(updateStickyOffsets).observe(topbar);
    }
    window.addEventListener('resize', function () { updateStickyOffsets(); updateMoreCount(); });
    updateStickyOffsets();
    updateMoreCount();

    /* ---------- 썸네일 ---------- */
    function makeTile(it) {
        var el = document.createElement('div');
        el.className = 'tile';
        el.dataset.id = it.id;
        el.title = it.name + '\n' + it.bs + (it.yr ? ' · ' + it.yr : '');
        el.innerHTML = '<img loading="lazy" decoding="async" alt=""><span class="tile-name"></span>';
        el.querySelector('img').src = 'thumb.php?id=' + it.id;
        el.querySelector('.tile-name').textContent = it.name;
        return el;
    }

    function renderGrid() {
        var list = state.items.filter(function (it) { return state.bs.has(it.bsid); });
        state.shown = list.length;
        grid.innerHTML = '';
        var frag = document.createDocumentFragment();
        list.forEach(function (it) { frag.appendChild(makeTile(it)); });
        grid.appendChild(frag);

        $('gridInfo').textContent = list.length.toLocaleString() + '장';
        var empty = list.length === 0;
        $('gridEmpty').hidden = !empty;
        $('gridEmptyMsg').textContent = !chips.length ? '이 계정이 선택한 이미지가 없습니다.' : '위에서 사업을 선택하면 해당 사업의 선택 이미지가 표시됩니다.';
        document.querySelectorAll('.dl-btn').forEach(function (b) { b.disabled = empty; });
    }

    function load() {
        FNJ.api({ act: 'picks', mem: MP.mem }).then(function (d) {
            state.items = d.items;
            renderGrid();
        }).catch(FNJ.silent);
    }

    $('dlForm').addEventListener('submit', function (e) {
        $('dlBs').value = bsParam();
        var orig = e.submitter && e.submitter.value === 'orig';
        var max = orig ? 500 : 1000;
        if (state.shown > max) { FNJ.toast('최대 ' + max.toLocaleString() + '장까지 다운로드됩니다.'); }
        else { FNJ.toast(orig ? 'NAS 원본을 모으는 중입니다. 장수가 많으면 시간이 걸립니다…' : 'ZIP 파일을 만드는 중입니다…'); }
    });

    // 본인 계정을 보는 중이면 메인 창의 선택 변경을 반영
    if (MP.self) { FNJ.onBroadcast(load); }

    syncBsUi();
    load();
})();
