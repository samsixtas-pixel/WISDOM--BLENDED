(function () {
    'use strict';
    function escapeHtml(value) { return String(value || '').replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function boot(root) {
        var input = root.querySelector('.ac-input');
        var hidden = root.querySelector('input[type="hidden"]');
        var list = root.querySelector('.ac-list');
        if (!input || !hidden || !list) return;
        var endpoint = root.dataset.endpoint || '', extra = JSON.parse(root.dataset.extra || '{}'), items = [], active = -1, timer;
        function close() { list.classList.remove('is-open'); active = -1; }
        function pick(item) { input.value = item.label; hidden.value = item.value; root.classList.add('has-value'); close(); root.dispatchEvent(new CustomEvent('ac:selected', {detail:item})); }
        function render(query) {
            list.innerHTML = items.length ? items.map(function (item, index) { return '<li class="ac-item' + (index === active ? ' is-active' : '') + '" data-index="' + index + '"><span class="ac-item__label">' + escapeHtml(item.label) + '</span><span class="ac-item__meta">' + escapeHtml(item.meta || '') + '</span></li>'; }).join('') : '<li class="ac-empty">No matches for "' + escapeHtml(query) + '"</li>';
            list.classList.add('is-open');
            list.querySelectorAll('.ac-item').forEach(function (row) { row.addEventListener('pointerdown', function (event) { event.preventDefault(); pick(items[Number(row.dataset.index)]); }); });
        }
        function fetchItems() {
            var query = input.value.trim();
            hidden.value = '';
            if (query.length < 1) return close();
            var params = new URLSearchParams(Object.assign({}, extra, {q:query}));
            fetch(endpoint + '?' + params.toString(), {headers:{Accept:'application/json'}}).then(function (response) { return response.json(); }).then(function (data) { items = Array.isArray(data.items) ? data.items : []; active = items.length ? 0 : -1; if (items.length === 1) pick(items[0]); else render(query); }).catch(close);
        }
        input.addEventListener('input', function () { root.classList.toggle('has-value', input.value !== ''); clearTimeout(timer); timer = setTimeout(fetchItems, 150); });
        input.addEventListener('keydown', function (event) { if (!list.classList.contains('is-open')) return; if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { active = (active + (event.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length; render(input.value); event.preventDefault(); } else if (event.key === 'Enter' && active >= 0) { pick(items[active]); event.preventDefault(); } else if (event.key === 'Escape') close(); });
        document.addEventListener('pointerdown', function (event) { if (!root.contains(event.target)) close(); });
        var clear = root.querySelector('.ac-clear');
        if (clear) clear.addEventListener('click', function () { input.value = ''; hidden.value = ''; root.classList.remove('has-value'); close(); input.focus(); root.dispatchEvent(new CustomEvent('ac:cleared')); });
    }
    window.WisdomAutocomplete = {boot:function () { document.querySelectorAll('.ac-wrap').forEach(boot); }};
    document.addEventListener('DOMContentLoaded', function () { window.WisdomAutocomplete.boot(); });
}());
