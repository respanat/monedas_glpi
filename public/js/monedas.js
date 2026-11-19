/**
 * Monedas - formato de moneda en la información financiera de GLPI.
 *
 * - En el formulario de "Gestión" (Infocom) cambia los campos de importe por
 *   uno visible con formato local ($ 4.109.900 COP) y deja el campo original
 *   oculto con el número limpio que se guarda en la base de datos.
 * - Da formato a los valores calculados de solo lectura (VNC, CTP...).
 * - Actualiza la vista previa en la pestaña "Moneda" de las entidades.
 *
 * @license GPL-3.0-or-later
 */
(function () {
    'use strict';

    var THOUSANDS = { dot: '.', comma: ',', space: ' ', none: '' };
    var DECIMALS = { comma: ',', dot: '.' };
    var AMOUNT_FIELDS = ['value', 'warranty_value'];
    var INFOCOM_FIELDS = ['value', 'warranty_value', 'sink_coeff', 'sink_time', 'warranty_duration'];
    var NBSP = ' ';

    var Fmt = {
        /** Solo el número: 4.109.900 */
        amount: function (n, cfg) {
            var neg = n < 0;
            var parts = Math.abs(n).toFixed(cfg.decimals).split('.');
            var t = cfg.thousands === ' ' ? NBSP : cfg.thousands;
            var out = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, t);
            if (cfg.decimals > 0) {
                out += cfg.decimal + parts[1];
            }
            return (neg ? '-' : '') + out;
        },

        /** Número con símbolo y código: $ 4.109.900 COP */
        full: function (n, cfg) {
            var num = Fmt.amount(n, cfg);
            var out = cfg.symbol_position === 'after' ? num + NBSP + cfg.symbol : cfg.symbol + NBSP + num;
            return cfg.show_code ? out + ' ' + cfg.currency_code : out;
        },

        /**
         * Lee un importe escrito con el formato configurado.
         * Misma lógica que Config::parse() en PHP.
         */
        parse: function (str, cfg) {
            var s = String(str).replace(/[^\d.,\-\s  ]/g, '').replace(/[\s  ]/g, '');
            if (s === '' || s === '-') {
                return null;
            }
            var t = cfg.thousands;
            var d = cfg.decimal;
            if (t !== '' && t !== ' ') {
                var parts = s.split(t);
                if (parts.length === 2 && s.indexOf(d) === -1 && parts[1].length !== 3) {
                    s = parts[0] + '.' + parts[1];
                } else {
                    s = parts.join('');
                }
            }
            s = s.split(d).join('.');
            var n = Number(s);
            return isNaN(n) ? null : n;
        },

        /**
         * Lee números que GLPI ya mostró con el formato del usuario
         * (ej. "1 137 833.43" o "1,137,833.43").
         */
        parseLoose: function (str) {
            var s = String(str).replace(/[\s  ]/g, '');
            if (!/^-?[\d.,]*\d[\d.,]*$/.test(s)) {
                return null;
            }
            var lastDot = s.lastIndexOf('.');
            var lastComma = s.lastIndexOf(',');
            if (lastDot !== -1 && lastComma !== -1) {
                var dec = lastDot > lastComma ? '.' : ',';
                var thou = dec === '.' ? ',' : '.';
                s = s.split(thou).join('').replace(dec, '.');
            } else if (lastDot !== -1 || lastComma !== -1) {
                var sep = lastDot !== -1 ? '.' : ',';
                var parts = s.split(sep);
                if (parts.length === 2 && parts[1].length !== 3) {
                    s = parts[0] + '.' + parts[1];
                } else {
                    s = parts.join('');
                }
            }
            var n = Number(s);
            return isNaN(n) ? null : n;
        }
    };

    window.MonedasFmt = Fmt;

    function baseUrl() {
        var root = (window.CFG_GLPI && window.CFG_GLPI.root_doc) || '';
        return root.replace(/\/$/, '') + '/plugins/monedas';
    }

    var configCache = {};

    function loadConfig(itemtype, itemsId) {
        var key = itemtype + ':' + itemsId;
        if (!configCache[key]) {
            var url = baseUrl() + '/ajax/config.php?itemtype=' + encodeURIComponent(itemtype) +
                '&items_id=' + encodeURIComponent(itemsId);
            configCache[key] = fetch(url, { credentials: 'same-origin' }).then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            });
        }
        return configCache[key];
    }

    /* ---------- Formulario de información financiera (Infocom) ---------- */

    function enhanceAmountInput(form, original, cfg) {
        if (!original || original.type === 'hidden' || original.dataset.monedas) {
            return;
        }
        original.dataset.monedas = '1';

        var visible = document.createElement('input');
        visible.type = 'text';
        visible.inputMode = 'decimal';
        visible.className = original.className;
        visible.classList.add('monedas-amount');
        visible.id = original.id ? original.id + '_monedas' : '';
        visible.disabled = original.disabled;
        visible.readOnly = original.readOnly;
        visible.setAttribute('autocomplete', 'off');
        if (original.id) {
            // La etiqueta <label for="..."> pasa a apuntar al campo visible.
            var label = form.querySelector('label[for="' + original.id + '"]');
            if (label) {
                label.htmlFor = visible.id;
            }
        }

        var n = Fmt.parse(original.value, { thousands: '', decimal: '.' });
        visible.value = n === null ? '' : Fmt.amount(n, cfg);

        var group = document.createElement('div');
        group.className = 'input-group monedas-group';

        var symbol = document.createElement('span');
        symbol.className = 'input-group-text';
        symbol.textContent = cfg.symbol;

        original.parentNode.insertBefore(group, original);
        if (cfg.symbol_position === 'after') {
            group.appendChild(visible);
            group.appendChild(symbol);
        } else {
            group.appendChild(symbol);
            group.appendChild(visible);
        }
        if (cfg.show_code) {
            var code = document.createElement('span');
            code.className = 'input-group-text monedas-code';
            code.textContent = cfg.currency_code;
            group.appendChild(code);
        }

        // El campo original se queda en el formulario, oculto, con el número limpio.
        original.type = 'hidden';
        group.appendChild(original);
        original.monedasVisible = visible;

        visible.addEventListener('input', function () {
            var v = Fmt.parse(visible.value, cfg);
            original.value = v === null ? '' : String(v);
        });
        visible.addEventListener('blur', function () {
            var v = Fmt.parse(visible.value, cfg);
            visible.value = v === null ? '' : Fmt.amount(v, cfg);
        });
    }

    /* ---------- Compra en moneda extranjera ---------- */

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') {
                node.textContent = attrs[k];
            } else if (k === 'className') {
                node.className = attrs[k];
            } else {
                node.setAttribute(k, attrs[k]);
            }
        });
        (children || []).forEach(function (c) { node.appendChild(c); });
        return node;
    }

    /** Decimales para mostrar una tasa: entre 2 y 6, según los que tenga. */
    function rateDecimals(rate) {
        var s = String(rate);
        var i = s.indexOf('.');
        var d = i === -1 ? 0 : s.length - i - 1;
        return Math.max(2, Math.min(6, d));
    }

    function formatRate(rate, cfg) {
        return Fmt.amount(rate, Object.assign({}, cfg, { decimals: rateDecimals(rate) }));
    }

    function enhancePurchase(form, cfg) {
        var valueInput = form.querySelector('input[name="value"]');
        if (!valueInput || !valueInput.monedasVisible || form.querySelector('.monedas-purchase')) {
            return;
        }
        var currencies = cfg.foreign_currencies || [];
        if (!currencies.length) {
            return;
        }

        var visibleValue = valueInput.monedasVisible;
        var locked = visibleValue.disabled || visibleValue.readOnly;
        var purchase = cfg.purchase;
        var byCode = {};
        currencies.forEach(function (c) { byCode[c.code] = c; });
        if (purchase && !byCode[purchase.currency_code]) {
            // Moneda guardada que ya no está en la lista (ej. cambió la moneda local).
            byCode[purchase.currency_code] = { code: purchase.currency_code, name: '', symbol: purchase.currency_code, decimals: 2 };
            currencies = currencies.concat([byCode[purchase.currency_code]]);
        }

        var uid = 'monedas_' + Math.random().toString(36).slice(2, 8);

        // Selector de moneda de compra.
        var select = el('select', { className: 'form-select', id: uid + '_cur', name: '_monedas_currency' }, [
            el('option', { value: cfg.currency_code, text: cfg.currency_code + ' – moneda local' })
        ].concat(currencies.map(function (c) {
            return el('option', { value: c.code, text: c.code + (c.name ? ' – ' + c.name : '') });
        })));

        // Valor en moneda extranjera (visible con formato + oculto con el número).
        var origSymbol = el('span', { className: 'input-group-text' });
        var origVisible = el('input', { className: 'form-control monedas-amount', id: uid + '_orig', inputmode: 'decimal', autocomplete: 'off' });
        var origHidden = el('input', { type: 'hidden', name: '_monedas_original_value' });

        var rateVisible = el('input', { className: 'form-control monedas-amount', id: uid + '_rate', inputmode: 'decimal', autocomplete: 'off' });
        var rateHidden = el('input', { type: 'hidden', name: '_monedas_rate' });
        var rateLocal = el('span', { className: 'input-group-text', text: cfg.symbol });

        var dateInput = el('input', { type: 'date', className: 'form-control', id: uid + '_date', name: '_monedas_rate_date' });

        var origLabel = el('label', { className: 'form-label', for: uid + '_orig', text: 'Valor de compra' });
        var rateLabel = el('label', { className: 'form-label', for: uid + '_rate', text: 'Tasa de cambio' });
        var helper = el('div', { className: 'monedas-purchase-helper small mt-2', role: 'status' });

        var foreignCols = [
            el('div', { className: 'col-md-3' }, [origLabel, el('div', { className: 'input-group' }, [origSymbol, origVisible]), origHidden]),
            el('div', { className: 'col-md-3' }, [rateLabel, el('div', { className: 'input-group' }, [rateLocal, rateVisible]), rateHidden]),
            el('div', { className: 'col-md-3' }, [el('label', { className: 'form-label', for: uid + '_date', text: 'Fecha de la tasa' }), dateInput])
        ];

        var panel = el('div', { className: 'monedas-purchase col-12 mb-3' }, [
            el('div', { className: 'row g-2 align-items-end' }, [
                el('div', { className: 'col-md-3' }, [
                    el('label', { className: 'form-label', for: uid + '_cur', text: 'Moneda de compra' }),
                    select
                ])
            ].concat(foreignCols)),
            helper
        ]);

        [select, origVisible, rateVisible, dateInput].forEach(function (i) { i.disabled = locked; });

        // Valores iniciales.
        if (purchase) {
            var pc = byCode[purchase.currency_code];
            select.value = purchase.currency_code;
            origVisible.value = Fmt.amount(purchase.original_value, Object.assign({}, cfg, { decimals: pc.decimals }));
            rateVisible.value = formatRate(purchase.exchange_rate, cfg);
            dateInput.value = purchase.rate_date || cfg.buy_date || '';
        } else {
            select.value = cfg.currency_code;
            dateInput.value = cfg.buy_date || '';
        }

        function update() {
            var code = select.value;
            var foreign = code !== cfg.currency_code;
            foreignCols.forEach(function (c) { c.classList.toggle('d-none', !foreign); });
            helper.classList.toggle('d-none', !foreign);

            if (!foreign) {
                origHidden.value = '';
                rateHidden.value = '';
                visibleValue.readOnly = locked;
                visibleValue.classList.remove('monedas-computed');
                return;
            }

            var cur = byCode[code];
            var fcfg = Object.assign({}, cfg, { symbol: cur.symbol, decimals: cur.decimals, currency_code: code });
            origSymbol.textContent = cur.symbol;
            origLabel.textContent = 'Valor de compra en ' + code;
            rateLabel.textContent = 'Tasa: 1 ' + code + ' en ' + cfg.currency_code;

            var orig = Fmt.parse(origVisible.value, cfg);
            var rate = Fmt.parse(rateVisible.value, cfg);
            origHidden.value = orig === null ? '' : String(orig);
            rateHidden.value = rate === null ? '' : String(rate);

            // El precio en moneda local lo calcula el plugin: no se escribe a mano.
            visibleValue.readOnly = true;
            visibleValue.classList.add('monedas-computed');

            if (orig !== null && rate !== null && rate > 0) {
                var local = Math.round(orig * rate * 10000) / 10000;
                valueInput.value = local.toFixed(4);
                visibleValue.value = Fmt.amount(local, cfg);
                helper.className = 'monedas-purchase-helper small mt-2 text-success';
                helper.textContent = Fmt.full(orig, Object.assign({}, fcfg, { show_code: true })) + ' × ' +
                    formatRate(rate, cfg) + ' = ' + Fmt.full(local, Object.assign({}, cfg, { show_code: true })) +
                    ' — este es el precio que usa la amortización.';
            } else {
                helper.className = 'monedas-purchase-helper small mt-2 text-warning';
                helper.textContent = 'Escribe el valor en ' + code + ' y la tasa de cambio para calcular el precio en ' + cfg.currency_code + '.';
            }
        }

        select.addEventListener('change', update);
        origVisible.addEventListener('input', update);
        rateVisible.addEventListener('input', update);
        origVisible.addEventListener('blur', function () {
            var n = Fmt.parse(origVisible.value, cfg);
            var cur = byCode[select.value];
            if (n !== null && cur) {
                origVisible.value = Fmt.amount(n, Object.assign({}, cfg, { decimals: cur.decimals }));
            }
        });
        rateVisible.addEventListener('blur', function () {
            var n = Fmt.parse(rateVisible.value, cfg);
            if (n !== null) {
                rateVisible.value = formatRate(n, cfg);
            }
        });

        var group = visibleValue.closest('.monedas-group');
        var field = (group && group.closest('.form-field')) || group;
        field.parentNode.insertBefore(panel, field);
        update();
    }

    var MONEY_LABEL =/(valor|value|vnc|ctp|tco|precio|price|cost)/i;

    function labelText(form, el) {
        var label = el.id ? form.querySelector('label[for="' + el.id + '"]') : null;
        if (!label) {
            var field = el.closest('.form-field, .row');
            label = field ? field.querySelector('label') : null;
        }
        return label ? label.textContent : '';
    }

    function enhanceReadonly(form, cfg) {
        var els = form.querySelectorAll('input[readonly], input[disabled]');
        Array.prototype.forEach.call(els, function (el) {
            if (el.dataset.monedas || el.classList.contains('monedas-amount')) {
                return;
            }
            // Un campo readonly con nombre sí se envía: no se toca para no alterar datos.
            if (el.name && (!el.disabled || INFOCOM_FIELDS.indexOf(el.name) !== -1)) {
                return;
            }
            // Solo campos cuya etiqueta habla de dinero (VNC, CTP, valor, precio...).
            if (!MONEY_LABEL.test(labelText(form, el))) {
                return;
            }
            var n = Fmt.parseLoose(el.value);
            if (n === null) {
                return;
            }
            el.dataset.monedas = '1';
            el.value = Fmt.full(n, cfg);
            el.classList.add('monedas-readonly');
        });
    }

    function enhanceInfocomForm(form) {
        if (form.dataset.monedasForm) {
            return;
        }
        form.dataset.monedasForm = '1';

        var itemtype = form.querySelector('input[name="itemtype"]');
        var itemsId = form.querySelector('input[name="items_id"]');

        loadConfig(itemtype ? itemtype.value : '', itemsId ? itemsId.value : '').then(function (cfg) {
            AMOUNT_FIELDS.forEach(function (name) {
                enhanceAmountInput(form, form.querySelector('input[name="' + name + '"]'), cfg);
            });
            enhancePurchase(form, cfg);
            enhanceReadonly(form, cfg);
        }).catch(function (e) {
            // Si falla, GLPI sigue funcionando igual que sin el plugin.
            if (window.console) {
                console.warn('[monedas] no se pudo cargar la configuración', e);
            }
        });
    }

    /* ---------- Pestaña "Moneda" en entidades: vista previa ---------- */

    function readConfigForm(box) {
        function field(name) {
            return box.querySelector('[data-monedas-field="' + name + '"]');
        }
        return {
            currency_code: field('currency_code').value,
            symbol: field('symbol').value,
            symbol_position: field('symbol_position').value,
            decimals: parseInt(field('decimals').value, 10) || 0,
            thousands: THOUSANDS[field('thousands_separator').value],
            decimal: DECIMALS[field('decimal_separator').value],
            show_code: field('show_code').checked
        };
    }

    function refreshPreview(box) {
        var cfg = readConfigForm(box);
        Array.prototype.forEach.call(box.querySelectorAll('[data-monedas-sample]'), function (el) {
            el.textContent = Fmt.full(parseFloat(el.getAttribute('data-monedas-sample')), cfg);
        });
        var warn = box.querySelector('[data-monedas-same-sep]');
        if (warn) {
            warn.classList.toggle('d-none', cfg.thousands !== cfg.decimal);
        }
    }

    function enhanceConfigForm(box) {
        if (box.dataset.monedasReady) {
            return;
        }
        box.dataset.monedasReady = '1';

        var currency = box.querySelector('[data-monedas-field="currency_code"]');
        currency.addEventListener('change', function () {
            // Al cambiar de moneda se sugieren su símbolo y sus decimales.
            var opt = currency.options[currency.selectedIndex];
            box.querySelector('[data-monedas-field="symbol"]').value = opt.getAttribute('data-symbol');
            box.querySelector('[data-monedas-field="decimals"]').value = opt.getAttribute('data-decimals');
        });

        box.addEventListener('input', function () { refreshPreview(box); });
        box.addEventListener('change', function () { refreshPreview(box); });
        refreshPreview(box);
    }

    /* ---------- Detección (las pestañas de GLPI se cargan por AJAX) ---------- */

    function scan(root) {
        if (!root.querySelectorAll) {
            return;
        }
        Array.prototype.forEach.call(root.querySelectorAll('form[action*="infocom.form.php"]'), enhanceInfocomForm);
        Array.prototype.forEach.call(root.querySelectorAll('[data-monedas-config-form]'), enhanceConfigForm);
    }

    var pending = false;
    function scheduleScan() {
        if (pending) {
            return;
        }
        pending = true;
        window.requestAnimationFrame(function () {
            pending = false;
            scan(document);
        });
    }

    function start() {
        scan(document);
        new MutationObserver(scheduleScan).observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
