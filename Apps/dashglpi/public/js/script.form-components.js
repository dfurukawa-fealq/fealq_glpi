// Componentes de formulário reutilizáveis do DashGLPI.
// Mantém o controle nativo como fonte de verdade e troca só a interação visual.
const DashFieldCombobox = (() => {
    const instances = new WeakMap();

    function normalize(value) {
        return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    }

    function optionLabel(option) {
        return String(option?.label || option?.text || option?.name || option?.completename || option?.display || '-');
    }

    function fromSelect(select) {
        return [...(select?.options || [])].map(option => ({
            id: option.value,
            label: option.textContent || option.label || option.value,
            disabled: option.disabled,
        }));
    }

    function bind(config) {
        const select = config.select;
        const input = config.input;
        const optionsBox = config.optionsBox;
        const toggle = config.toggle || null;
        if (!select || !input || !optionsBox) return null;

        const current = instances.get(select);
        if (current) return current;

        let options = [];

        const api = {
            setOptions(nextOptions = null) {
                options = Array.isArray(nextOptions) ? nextOptions : fromSelect(select);
                api.sync();
            },
            sync() {
                const selected = options.find(item => String(item.id) === String(select.value))
                    || options.find(item => !item.disabled)
                    || null;
                input.value = selected ? optionLabel(selected) : '';
                input.disabled = select.disabled;
                if (toggle) toggle.disabled = select.disabled;
            },
            render(query = input.value, open = true) {
                const terms = normalize(query).split(/\s+/).filter(Boolean);
                const filtered = options
                    .filter(item => !item.disabled)
                    .filter(item => {
                        if (!terms.length) return true;
                        const haystack = normalize([optionLabel(item), item.name, item.completename, item.display].join(' '));
                        return terms.every(term => haystack.includes(term));
                    })
                    .slice(0, config.limit || 80);

                if (!filtered.length) {
                    optionsBox.innerHTML = `<div class="ticket-create-combobox-empty">${escHtml(config.emptyText || 'Nenhum resultado encontrado.')}</div>`;
                } else {
                    optionsBox.innerHTML = filtered.map(item => {
                        const value = String(item.id ?? '');
                        const active = value === String(select.value) ? ' is-selected' : '';
                        return `<button type="button" class="ticket-create-combobox-option${active}" role="option" data-dash-field-value="${escHtml(value)}">${escHtml(optionLabel(item))}</button>`;
                    }).join('');
                }

                optionsBox.hidden = !open;
                input.setAttribute('aria-expanded', open ? 'true' : 'false');
            },
            selectValue(value, dispatch = true) {
                const item = options.find(option => String(option.id) === String(value));
                if (!item) return;
                select.value = String(item.id ?? '');
                input.value = optionLabel(item);
                api.close();
                if (dispatch) {
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            },
            commitTyped() {
                const typed = normalize(input.value);
                const exact = options.find(item => normalize(optionLabel(item)) === typed);
                if (exact) api.selectValue(exact.id, true);
                else api.sync();
            },
            close() {
                optionsBox.hidden = true;
                input.setAttribute('aria-expanded', 'false');
            },
        };

        input.addEventListener('focus', () => api.render(input.value, true));
        input.addEventListener('input', () => api.render(input.value, true));
        input.addEventListener('blur', () => setTimeout(api.commitTyped, 120));
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                api.close();
                return;
            }
            if (event.key === 'Enter') {
                const first = optionsBox.querySelector('[data-dash-field-value]');
                if (first) {
                    event.preventDefault();
                    api.selectValue(first.getAttribute('data-dash-field-value'), true);
                }
            }
        });
        optionsBox.addEventListener('mousedown', event => {
            const item = event.target.closest('[data-dash-field-value]');
            if (!item) return;
            event.preventDefault();
            api.selectValue(item.getAttribute('data-dash-field-value'), true);
        });
        toggle?.addEventListener('click', () => api.render('', true));
        select.addEventListener('change', api.sync);
        document.addEventListener('click', event => {
            if (!event.target.closest(config.rootSelector || '.ticket-create-combobox')) api.close();
        });

        api.setOptions(config.options || null);
        instances.set(select, api);
        return api;
    }

    return { bind };
})();
