// 신규 이미지 등록: 사업 선택 → 파일 담기(드래그·선택) → 한 장씩 업로드 → 결과 썸네일 확인
(function () {
    'use strict';

    var $ = function (id) { return document.getElementById(id); };
    var cfg = window.UPLOAD, toast = FNJ.toast;
    var LS_UP = 'fnjimg.upbs';
    var qList = $('qList'), dropzone = $('dropzone'), fileInput = $('fileInput');

    var queue = [];    // { file, el, state: wait|up|done|fail }
    var busy = false;

    /* ---------- 사업 선택 (한 개만) ---------- */
    var bar = FNJ.initBsBar(false, onBs);

    function bsId() { return bar.selected.size ? Array.from(bar.selected)[0] : 0; }

    function onBs() {
        var name = bar.names()[0] || '';
        FNJ.store(LS_UP, name);
        $('upTarget').textContent = name
            ? '저장 위치: ' + cfg.root + '\\' + name + '  (폴더가 없으면 자동으로 만듭니다)'
            : '위에서 이미지를 넣을 사업을 하나 선택해 주세요.';
        refresh();
    }

    /* ---------- 대기 목록 ---------- */
    function fmtSize(n) {
        return n >= 1048576 ? (n / 1048576).toFixed(1) + 'MB' : Math.max(1, Math.round(n / 1024)) + 'KB';
    }

    function extOf(name) {
        var i = name.lastIndexOf('.');
        return i < 0 ? '' : name.slice(i + 1).toLowerCase();
    }

    function addFiles(list) {
        if (!cfg.enabled) { return; }
        var skipped = [];
        Array.from(list).forEach(function (f) {
            if (cfg.exts.indexOf(extOf(f.name)) < 0) { skipped.push(f.name + ' (형식)'); return; }
            if (f.size > cfg.maxBytes) { skipped.push(f.name + ' (용량)'); return; }
            var dup = queue.some(function (q) {
                return q.state !== 'done' && q.file.name === f.name && q.file.size === f.size && q.file.lastModified === f.lastModified;
            });
            if (dup) { return; }
            var item = { file: f, state: 'wait' };
            makeQTile(item);
            qList.appendChild(item.el);
            queue.push(item);
        });
        if (skipped.length) {
            toast(skipped.length + '개 제외: ' + skipped.slice(0, 2).join(', ') + (skipped.length > 2 ? ' …' : ''));
        }
        refresh();
    }

    // 대기 목록 타일을 만들어 item.el 에 연결
    function makeQTile(item) {
        var el = item.el = document.createElement('div');
        el.className = 'qtile';
        el.title = item.file.name;
        var img = document.createElement('img');
        img.alt = '';
        item.url = URL.createObjectURL(item.file);
        img.src = item.url;
        img.onerror = function () { img.remove(); el.classList.add('noprev'); };  // 브라우저가 못 보여주는 형식 (bmp 일부 등)
        el.appendChild(img);
        el.insertAdjacentHTML('beforeend',
            '<span class="qtile-ext"></span>' +
            '<button type="button" class="ptile-x" aria-label="목록에서 빼기">×</button>' +
            '<div class="qtile-info"><span class="qtile-name"></span><span class="qtile-state"></span></div>' +
            '<i class="qtile-bar"></i>');
        el.querySelector('.qtile-ext').textContent = extOf(item.file.name).toUpperCase();
        el.querySelector('.qtile-name').textContent = item.file.name;
        setState(item, 'wait');
        el.querySelector('.ptile-x').addEventListener('click', function () {
            if (busy) { return; }
            removeItem(item);
            refresh();
        });
    }

    function removeItem(item) {
        URL.revokeObjectURL(item.url);
        item.el.remove();
        queue.splice(queue.indexOf(item), 1);
    }

    function setState(item, st, info) {
        item.state = st;
        item.el.className = 'qtile ' + st + (item.el.classList.contains('noprev') ? ' noprev' : '');
        var label = { wait: fmtSize(item.file.size), up: '업로드 중', done: '완료', fail: '실패' }[st];
        item.el.querySelector('.qtile-state').textContent = info ? label + ' · ' + info : label;
        if (st !== 'up') { item.el.querySelector('.qtile-bar').style.width = st === 'done' ? '100%' : '0'; }
    }

    function pending() {
        return queue.filter(function (q) { return q.state === 'wait' || q.state === 'fail'; });
    }

    function refresh() {
        var n = pending().length;
        $('upCount').textContent = n.toLocaleString() + '장';
        $('upStart').disabled = busy;  // 조건이 안 맞으면 누를 때 안내 (업로드 중에만 잠금)
        $('qClear').disabled = busy || !queue.length;
        dropzone.classList.toggle('compact', queue.length > 0);
    }

    fileInput.addEventListener('change', function () {
        addFiles(fileInput.files);
        fileInput.value = '';  // 같은 파일을 다시 고를 수 있도록
    });

    ['dragenter', 'dragover'].forEach(function (t) {
        dropzone.addEventListener(t, function (e) {
            e.preventDefault();
            if (cfg.enabled && !busy) { dropzone.classList.add('over'); }
        });
    });
    ['dragleave', 'drop'].forEach(function (t) {
        dropzone.addEventListener(t, function (e) {
            e.preventDefault();
            if (t === 'dragleave' && dropzone.contains(e.relatedTarget)) { return; }
            dropzone.classList.remove('over');
        });
    });
    dropzone.addEventListener('drop', function (e) {
        if (!busy && e.dataTransfer) { addFiles(e.dataTransfer.files); }
    });
    // 드롭존 밖에 떨어뜨려도 브라우저가 이미지를 열지 않도록
    window.addEventListener('dragover', function (e) { e.preventDefault(); });
    window.addEventListener('drop', function (e) { e.preventDefault(); });

    $('qClear').addEventListener('click', function () {
        if (busy) { return; }
        queue.slice().forEach(removeItem);
        refresh();
    });

    /* ---------- 업로드 (한 장씩) ---------- */
    function send(item, bs, onProgress) {
        return new Promise(function (resolve) {
            var fd = new FormData();
            fd.append('act', 'upload');
            fd.append('bs', bs);
            fd.append('mtime', item.file.lastModified || '');
            fd.append('file', item.file, item.file.name);
            var x = new XMLHttpRequest();
            x.open('POST', 'upload_api.php');
            x.setRequestHeader('X-CSRF-Token', APP.csrf);
            x.upload.onprogress = function (e) { if (e.lengthComputable) { onProgress(e.loaded / e.total); } };
            x.onload = function () {
                var d;
                try { d = JSON.parse(x.responseText); } catch (e) {
                    d = { ok: false, error: x.status === 413 ? '파일이 너무 큽니다.' : '서버 응답 오류 (' + x.status + ')' };
                }
                if (d.login) {
                    alert(d.error || '불법적인 접속입니다.');
                    location.replace('login.php');
                }
                resolve(d);
            };
            x.onerror = function () { resolve({ ok: false, error: '네트워크 오류' }); };
            x.send(fd);
        });
    }

    $('upStart').addEventListener('click', function () {
        var items = pending(), bs = bsId();
        if (busy) { return; }
        if (!cfg.enabled) { alert('이 서버에서는 이미지를 등록할 수 없습니다. 화면 위의 안내를 확인해 주세요.'); return; }
        if (!bs) { alert('사업을 선택해 주세요.'); return; }
        if (!items.length) { alert('업로드할 원본 이미지를 선택해 주세요.'); return; }
        busy = true;
        refresh();

        var bsName = bar.names()[0];
        var totalBytes = items.reduce(function (s, q) { return s + q.file.size; }, 0) || 1;
        var doneBytes = 0, okN = 0, failN = 0, results = [];
        var prog = $('upProgress');
        prog.hidden = false;

        function overall(cur) {
            var pct = Math.min(100, Math.round((doneBytes + cur) / totalBytes * 100));
            $('upBar').style.width = pct + '%';
            $('upProgText').textContent = (okN + failN) + ' / ' + items.length + '장 · ' + pct + '%';
        }
        overall(0);

        items.reduce(function (p, item) {
            return p.then(function () {
                setState(item, 'up');
                var tileBar = item.el.querySelector('.qtile-bar');
                return send(item, bs, function (r) {
                    tileBar.style.width = Math.round(r * 100) + '%';
                    overall(item.file.size * r);
                }).then(function (d) {
                    doneBytes += item.file.size;
                    if (d.ok) {
                        okN++;
                        setState(item, 'done', d.renamed ? d.name + ' 으로 저장' : '');
                        results.push(d);
                    } else {
                        failN++;
                        setState(item, 'fail', d.error || '오류');
                    }
                    overall(0);
                });
            });
        }, Promise.resolve()).then(function () {
            busy = false;
            showResults(results, bsName);
            bumpChipCount(bs, okN);
            // 완료된 항목은 결과로 옮기고 목록에서 뺌 (실패한 것만 남겨 다시 시도)
            queue.filter(function (q) { return q.state === 'done'; }).forEach(removeItem);
            refresh();
            toast(failN ? okN + '장 등록, ' + failN + '장 실패 — 실패한 파일은 다시 업로드할 수 있습니다.' : okN + '장을 등록했습니다.');
            if (!queue.length) { setTimeout(function () { prog.hidden = true; }, 1500); }
        });
    });

    /* ---------- 결과: DB 에 저장된 썸네일로 확인 ---------- */
    function showResults(results, bsName) {
        if (!results.length) { return; }
        var grid = $('resultGrid');
        grid.innerHTML = '';
        results.forEach(function (d) {
            var el = document.createElement('div');
            el.className = 'tile show-name';
            el.title = d.name + '\n' + d.w + '×' + d.h;
            var img = document.createElement('img');
            img.alt = '';
            img.src = 'thumb.php?id=' + d.id;
            el.appendChild(img);
            var nm = document.createElement('span');
            nm.className = 'tile-name';
            nm.textContent = d.name;
            el.appendChild(nm);
            grid.appendChild(el);
        });
        $('resultInfo').textContent = bsName + ' · ' + results.length.toLocaleString() + '장';
        $('resultPath').textContent = results[0].folder;
        $('goCheck').onclick = function () { FNJ.store('fnjimg.bs', JSON.stringify([bsName])); };
        $('resultCard').hidden = false;
        $('resultCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function bumpChipCount(bs, n) {
        var c = bar.chips.filter(function (x) { return +x.dataset.id === bs; })[0];
        var cnt = c && c.querySelector('.chip-cnt');
        if (cnt && n) { cnt.textContent = (parseInt(cnt.textContent.replace(/,/g, ''), 10) + n).toLocaleString(); }
    }

    /* ---------- 시작 ---------- */
    var saved = FNJ.load(LS_UP);
    if (saved) { bar.restore([saved]); }
    onBs();
})();
