/**
 * Lightweight type-ahead autocomplete for city / location / place search
 * fields, replacing native <select> dropdowns across Travelix admin and
 * trip-planning pages. No dependencies.
 *
 * Usage:
 *   const ac = travelixAutocomplete(inputEl, {
 *     getItems: () => [{ value: 'Lahore', label: 'Lahore' }, ...],
 *     onSelect: (item) => { ... },
 *     onClear:  () => { ... },   // fired when the input is cleared/invalidated
 *     strict:   true,            // revert to last valid value if left unmatched on blur
 *     minChars: 0,
 *     maxResults: 50,
 *     emptyText: 'No matches found'
 *   });
 *
 *   ac.setValue('Lahore');  // programmatically select without opening the list
 *   ac.refresh();           // re-read getItems() (e.g. after an async load)
 */
(function () {
    if (window.travelixAutocomplete) return;

    function injectStyles() {
        if (document.getElementById('travelix-ac-styles')) return;
        const style = document.createElement('style');
        style.id = 'travelix-ac-styles';
        style.textContent = `
            .travelix-ac-wrap { position: relative; }
            .travelix-ac-list {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                z-index: 500;
                background: #fff;
                border: 1px solid rgba(15,23,42,0.12);
                border-radius: 12px;
                margin-top: 6px;
                max-height: 240px;
                overflow-y: auto;
                box-shadow: 0 12px 30px rgba(15,23,42,0.14);
            }
            .travelix-ac-list.show { display: block; }
            .travelix-ac-item {
                padding: 10px 14px;
                font-size: 14px;
                color: #1e293b;
                cursor: pointer;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .travelix-ac-item:hover,
            .travelix-ac-item.active { background: #eef2ff; }
            .travelix-ac-empty {
                padding: 10px 14px;
                font-size: 13px;
                color: #94a3b8;
            }
        `;
        document.head.appendChild(style);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    window.travelixAutocomplete = function (input, options) {
        injectStyles();

        const opts = Object.assign({
            getItems: () => [],
            onSelect: null,
            onClear: null,
            strict: true,
            minChars: 0,
            maxResults: 50,
            emptyText: 'No matches found'
        }, options || {});

        let wrap = input.parentElement;
        if (!wrap || !wrap.classList.contains('travelix-ac-wrap')) {
            wrap = document.createElement('div');
            wrap.className = 'travelix-ac-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);
        }

        const list = document.createElement('div');
        list.className = 'travelix-ac-list';
        wrap.appendChild(list);

        input.setAttribute('autocomplete', 'off');

        let currentItems = [];
        let activeIndex = -1;
        let lastValidValue = input.value || '';

        function render(query) {
            const q = query.trim().toLowerCase();
            const all = opts.getItems() || [];

            if (q.length < opts.minChars) {
                list.classList.remove('show');
                list.innerHTML = '';
                currentItems = [];
                return;
            }

            currentItems = (q
                ? all.filter((it) => it.label.toLowerCase().includes(q))
                : all
            ).slice(0, opts.maxResults);

            activeIndex = -1;

            if (!currentItems.length) {
                list.innerHTML = q ? `<div class="travelix-ac-empty">${escapeHtml(opts.emptyText)}</div>` : '';
                list.classList.toggle('show', !!q);
                return;
            }

            list.innerHTML = currentItems
                .map((it, i) => `<div class="travelix-ac-item" data-index="${i}">${escapeHtml(it.label)}</div>`)
                .join('');
            list.classList.add('show');
        }

        function updateActiveClass() {
            list.querySelectorAll('.travelix-ac-item').forEach((el, i) => {
                el.classList.toggle('active', i === activeIndex);
            });
            list.querySelector('.travelix-ac-item.active')?.scrollIntoView({ block: 'nearest' });
        }

        function selectItem(item) {
            input.value = item.label;
            lastValidValue = item.label;
            list.classList.remove('show');
            list.innerHTML = '';
            opts.onSelect?.(item);
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        input.addEventListener('input', () => render(input.value));

        input.addEventListener('focus', () => render(input.value));

        input.addEventListener('blur', () => {
            // Delay so a click on a list item registers before the list is hidden.
            setTimeout(() => {
                list.classList.remove('show');

                if (!opts.strict) return;

                const typed = input.value.trim();
                if (typed === lastValidValue) return;

                const all = opts.getItems() || [];
                const exact = all.find((it) => it.label.toLowerCase() === typed.toLowerCase());

                if (exact) {
                    input.value = exact.label;
                    lastValidValue = exact.label;
                    opts.onSelect?.(exact);
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } else if (typed === '') {
                    lastValidValue = '';
                    opts.onClear?.();
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    input.value = lastValidValue;
                }
            }, 150);
        });

        list.addEventListener('mousedown', (e) => {
            e.preventDefault();
            const el = e.target.closest('.travelix-ac-item');
            if (!el) return;
            const item = currentItems[Number(el.dataset.index)];
            if (item) selectItem(item);
        });

        input.addEventListener('keydown', (e) => {
            if (!list.classList.contains('show') || !currentItems.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, currentItems.length - 1);
                updateActiveClass();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                updateActiveClass();
            } else if (e.key === 'Enter') {
                if (activeIndex >= 0 && currentItems[activeIndex]) {
                    e.preventDefault();
                    selectItem(currentItems[activeIndex]);
                }
            } else if (e.key === 'Escape') {
                list.classList.remove('show');
            }
        });

        return {
            refresh() { render(input.value); },
            setValue(label) {
                input.value = label || '';
                lastValidValue = input.value;
            },
            clear() {
                input.value = '';
                lastValidValue = '';
                list.classList.remove('show');
                list.innerHTML = '';
            }
        };
    };
})();
