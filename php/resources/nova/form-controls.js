// Nova draws selects and date fields with the browser's native controls, so
// their open list and calendar ignore the admin theme. This puts a styled
// trigger and popup in front of each one (forms, action modals, metric
// ranges, the inline filters) and keeps the native element as the source of
// truth: Nova's components stay bound to it, and a pick sets its value and
// fires the input and change events a user's pick would. Multiple selects
// are left alone.
(() => {
    const CONTROLS =
        'select:not([multiple]):not([data-mf-control]), input[type="date"]:not([data-mf-control])';
    const GAP = 4;
    const EDGE = 8;

    const icon = (className, paths) =>
        `<svg class="mf-icon ${className}" viewBox="0 0 16 16" aria-hidden="true">${paths}</svg>`;
    const ICONS = {
        chevron: icon('mf-control__chevron', '<path d="m4 6 4 4 4-4"/>'),
        calendar: icon(
            '',
            '<rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3"/>',
        ),
        check: icon('', '<path d="m3.5 8.5 3 3 6-7"/>'),
        previous: icon('', '<path d="m10 4-4 4 4 4"/>'),
        next: icon('', '<path d="m6 4 4 4-4 4"/>'),
    };

    // --- Dates: "YYYY-MM-DD" strings, read in local time -------------------

    const pad = (number) => String(number).padStart(2, '0');
    const toIso = (date) =>
        `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

    function parseIso(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        if (!match) {
            return null;
        }
        const date = new Date(+match[1], +match[2] - 1, +match[3]);
        return toIso(date) === value ? date : null;
    }

    const addDays = (date, days) =>
        new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);

    // Keeps the day of the month where it can: Jan 31 + 1 month -> Feb 28.
    function addMonths(date, months) {
        const year = date.getFullYear();
        const month = date.getMonth() + months;
        const lastDay = new Date(year, month + 1, 0).getDate();
        return new Date(year, month, Math.min(date.getDate(), lastDay));
    }

    function today() {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    }

    const dayFormat = new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
    const monthFormat = new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric',
    });
    const MONTHS = Array.from({ length: 12 }, (_, month) =>
        new Date(2000, month, 1).toLocaleString('en-US', { month: 'short' }),
    );
    const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];

    // --- Shared plumbing ---------------------------------------------------

    let lastId = 0;
    const nextId = () => `mf-control-${++lastId}`;

    const isPrintable = (event) =>
        event.key.length === 1 &&
        !event.ctrlKey &&
        !event.metaKey &&
        !event.altKey;

    // Vue sets `value` and `selectedIndex` as properties, which fires no
    // event, so the trigger is redrawn from the property setters.
    function onAssign(element, properties, callback) {
        const prototype = Object.getPrototypeOf(element);
        for (const property of properties) {
            const { get, set } = Object.getOwnPropertyDescriptor(
                prototype,
                property,
            );
            Object.defineProperty(element, property, {
                configurable: true,
                get() {
                    return get.call(this);
                },
                set(value) {
                    set.call(this, value);
                    callback();
                },
            });
        }
    }

    // Nova's Select and BelongsTo fields do not point their <label> at the
    // control, so the label of the field row around it is used instead.
    function rowLabel(control) {
        for (
            let node = control.parentElement;
            node;
            node = node.parentElement
        ) {
            const fields = node.querySelectorAll(
                'input:not([type="hidden"]), select, textarea',
            );
            if (fields.length > 1) {
                return null;
            }
            const label = node.querySelector('label');
            if (label) {
                return label;
            }
        }
        return null;
    }

    function labelOf(control) {
        const own = control.getAttribute('aria-label');
        if (own) {
            return own;
        }
        const label = control.labels?.[0] ?? rowLabel(control);
        if (!label) {
            return null;
        }
        const copy = label.cloneNode(true);
        copy.querySelectorAll('select, input, button').forEach((node) =>
            node.remove(),
        );
        const text = copy.textContent.replace(/\s+/g, ' ').trim();
        // Nova marks required fields with a trailing "*".
        return text.replace(/\s*\*$/, '') || null;
    }

    function createTrigger(control, popupRole, iconHtml) {
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-haspopup', popupRole);
        trigger.setAttribute('aria-expanded', 'false');
        const name = labelOf(control);
        if (name) {
            trigger.setAttribute('aria-label', name);
        }
        trigger.innerHTML = `<span class="mf-control__value"></span>${iconHtml}`;

        // The native control stays for Nova, hidden and out of the tab order.
        // A click on its <label> focuses it, so focus is handed on.
        control.dataset.mfControl = '';
        control.tabIndex = -1;
        control.setAttribute('aria-hidden', 'true');
        control.addEventListener('focus', () => trigger.focus());
        control.after(trigger);

        return trigger;
    }

    // Copies Nova's classes, so the trigger has the native control's size,
    // border and error state. Nova greys out read-only controls, which a
    // button always is; "form-select" exempts it, as for Nova's own
    // searchable select.
    function syncTrigger(trigger, control) {
        trigger.className = `${control.className} form-select mf-control`;
        trigger.disabled = control.disabled || control.readOnly;
    }

    function emit(control) {
        control.dispatchEvent(new Event('input', { bubbles: true }));
        control.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // The popup is a manual popover, so it is drawn in the top layer, above
    // Nova's modals and never clipped by a scrolling parent. Inside a modal it
    // is placed in the modal's content, which keeps it inside the modal's
    // focus trap. Returns a function that closes it.
    function openPopup(trigger, panel, onClosed) {
        panel.setAttribute('popover', 'manual');
        (trigger.closest('.modal > div') ?? document.body).append(panel);
        panel.showPopover?.();
        trigger.setAttribute('aria-expanded', 'true');
        trigger.setAttribute('aria-controls', panel.id);

        const place = () => {
            const rect = trigger.getBoundingClientRect();
            panel.style.minWidth = `${rect.width}px`;
            const { offsetWidth: width, offsetHeight: height } = panel;
            const fitsBelow =
                rect.bottom + GAP + height <= window.innerHeight - EDGE;
            const fitsAbove = rect.top - GAP - height >= EDGE;
            const top =
                !fitsBelow && fitsAbove
                    ? rect.top - GAP - height
                    : rect.bottom + GAP;
            const left = Math.min(rect.left, window.innerWidth - width - EDGE);
            panel.style.top = `${top}px`;
            panel.style.left = `${Math.max(EDGE, left)}px`;
        };
        const onPointerDown = (event) => {
            if (
                !trigger.contains(event.target) &&
                !panel.contains(event.target)
            ) {
                close();
            }
        };
        const resizes = new ResizeObserver(place);

        function close() {
            resizes.disconnect();
            window.removeEventListener('resize', place);
            window.removeEventListener('scroll', place, true);
            document.removeEventListener('pointerdown', onPointerDown, true);
            panel.remove();
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-controls');
            onClosed();
        }

        place();
        resizes.observe(panel);
        window.addEventListener('resize', place);
        window.addEventListener('scroll', place, true);
        document.addEventListener('pointerdown', onPointerDown, true);

        return close;
    }

    // --- Select ------------------------------------------------------------

    function enhanceSelect(select) {
        const trigger = createTrigger(select, 'listbox', ICONS.chevron);
        const value = trigger.firstElementChild;
        let list = null;
        let close = null;
        let active = 0;
        let typed = { text: '', at: 0 };

        // Placeholders such as "Choose an option" are not offered as choices.
        const choices = () =>
            [...select.options].filter(
                (option) => !(option.disabled && option.value === ''),
            );
        const current = () => select.options[select.selectedIndex];

        function render() {
            syncTrigger(trigger, select);
            const option = current();
            value.textContent = option?.text ?? '';
            value.classList.toggle(
                'mf-control__placeholder',
                !option || option.value === '',
            );
            if (list) {
                renderList();
            }
        }

        function renderList() {
            const selected = current();
            list.replaceChildren(
                ...choices().map((option, index) => {
                    const item = document.createElement('li');
                    item.id = `${list.id}-${index}`;
                    item.className = 'mf-listbox__option';
                    item.setAttribute('role', 'option');
                    item.setAttribute(
                        'aria-selected',
                        String(option === selected),
                    );
                    if (option.disabled) {
                        item.setAttribute('aria-disabled', 'true');
                    }
                    const text = document.createElement('span');
                    text.textContent = option.text;
                    item.append(text);
                    if (option === selected) {
                        item.insertAdjacentHTML('beforeend', ICONS.check);
                    }
                    return item;
                }),
            );
            setActive(Math.min(active, list.children.length - 1));
        }

        function setActive(index) {
            list.children[active]?.classList.remove('is-active');
            active = Math.max(index, 0);
            const item = list.children[active];
            item?.classList.add('is-active');
            item?.scrollIntoView({ block: 'nearest' });
            trigger.setAttribute('aria-activedescendant', item?.id ?? '');
        }

        // The next enabled option from `from` in direction `step`.
        function step(from, direction) {
            const options = choices();
            for (
                let index = from + direction;
                index >= 0 && index < options.length;
                index += direction
            ) {
                if (!options[index].disabled) {
                    return index;
                }
            }
            return from;
        }

        // Typing jumps to the next option that starts with the typed text.
        function find(key) {
            const now = Date.now();
            const text =
                (now - typed.at < 700 ? typed.text : '') + key.toLowerCase();
            typed = { text, at: now };

            const options = choices();
            const from = list ? active : options.indexOf(current());
            const start = text.length === 1 ? from + 1 : Math.max(from, 0);
            for (let offset = 0; offset < options.length; offset++) {
                const index = (start + offset) % options.length;
                const option = options[index];
                if (
                    !option.disabled &&
                    option.text.toLowerCase().startsWith(text)
                ) {
                    return index;
                }
            }
            return -1;
        }

        function show() {
            list = document.createElement('ul');
            list.id = nextId();
            list.className = 'mf-popup mf-listbox';
            list.setAttribute('role', 'listbox');
            // Keeps focus on the trigger while an option is clicked.
            list.addEventListener('mousedown', (event) =>
                event.preventDefault(),
            );
            list.addEventListener('mousemove', (event) => {
                const item = event.target.closest('[role="option"]');
                if (item && item !== list.children[active]) {
                    setActive([...list.children].indexOf(item));
                }
            });
            list.addEventListener('click', (event) => {
                const item = event.target.closest('[role="option"]');
                if (item) {
                    choose([...list.children].indexOf(item));
                }
            });

            active = Math.max(choices().indexOf(current()), 0);
            renderList();
            close = openPopup(trigger, list, () => {
                list = null;
                close = null;
                trigger.removeAttribute('aria-activedescendant');
            });
            setActive(active);
        }

        function choose(index) {
            const option = choices()[index];
            if (!option || option.disabled) {
                return;
            }
            close?.();
            trigger.focus();
            if (option.index !== select.selectedIndex) {
                select.selectedIndex = option.index;
                emit(select);
            }
        }

        function onKeyDown(event) {
            const { key } = event;
            if (!list) {
                if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(key)) {
                    show();
                } else if (isPrintable(event)) {
                    // Like a native select: typing while closed picks.
                    choose(find(key));
                } else {
                    return;
                }
            } else if (key === 'ArrowDown') {
                setActive(step(active, 1));
            } else if (key === 'ArrowUp') {
                setActive(step(active, -1));
            } else if (key === 'Home' || key === 'PageUp') {
                setActive(step(-1, 1));
            } else if (key === 'End' || key === 'PageDown') {
                setActive(step(choices().length, -1));
            } else if (key === 'Enter' || key === ' ') {
                choose(active);
            } else if (key === 'Escape') {
                close();
            } else if (key === 'Tab') {
                close();
                return;
            } else if (isPrintable(event)) {
                const index = find(key);
                if (index !== -1) {
                    setActive(index);
                }
            } else {
                return;
            }
            event.preventDefault();
            // Keeps Nova's keyboard shortcuts and a modal's Escape out of it.
            event.stopPropagation();
        }

        trigger.addEventListener('click', () => (close ? close() : show()));
        trigger.addEventListener('keydown', onKeyDown);
        // Firefox clicks a button on Space key-up even when key-down was
        // handled; the key-down already did the work.
        trigger.addEventListener('keyup', (event) => {
            if (event.key === ' ') {
                event.preventDefault();
            }
        });
        onAssign(select, ['value', 'selectedIndex'], render);

        return { control: select, render, close: () => close?.(), trigger };
    }

    // --- Date --------------------------------------------------------------

    function enhanceDate(input) {
        const trigger = createTrigger(input, 'dialog', ICONS.calendar);
        const value = trigger.firstElementChild;
        let panel = null;
        let close = null;
        // The focused day; the calendar shows its month.
        let cursor = today();
        let view = 'days';
        let focusCursor = false;

        const allowed = (iso) =>
            (!input.min || iso >= input.min) &&
            (!input.max || iso <= input.max);

        function render() {
            syncTrigger(trigger, input);
            const date = parseIso(input.value);
            value.textContent = date
                ? dayFormat.format(date)
                : input.getAttribute('placeholder') || 'Choose a date';
            value.classList.toggle('mf-control__placeholder', !date);
        }

        function headHtml() {
            const days = view === 'days';
            const title = days
                ? monthFormat.format(cursor)
                : String(cursor.getFullYear());
            const unit = days ? 'month' : 'year';
            return `<div class="mf-calendar__head">
                <button type="button" class="mf-calendar__nav" data-key="previous" data-months="${days ? -1 : -12}" aria-label="Previous ${unit}">${ICONS.previous}</button>
                <button type="button" class="mf-calendar__title" data-key="title" data-action="view" aria-label="${title}, ${days ? 'choose month' : 'back to days'}">${title}</button>
                <button type="button" class="mf-calendar__nav" data-key="next" data-months="${days ? 1 : 12}" aria-label="Next ${unit}">${ICONS.next}</button>
            </div>`;
        }

        function daysHtml() {
            const todayIso = toIso(today());
            const cursorIso = toIso(cursor);
            const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
            const start = addDays(first, -first.getDay());

            let rows = '';
            for (let week = 0; week < 6; week++) {
                let cells = '';
                for (let weekday = 0; weekday < 7; weekday++) {
                    const date = addDays(start, week * 7 + weekday);
                    const iso = toIso(date);
                    const classes = [
                        'mf-calendar__day',
                        date.getMonth() !== cursor.getMonth() && 'is-outside',
                        iso === todayIso && 'is-today',
                        iso === input.value && 'is-selected',
                    ]
                        .filter(Boolean)
                        .join(' ');
                    const attributes = [
                        iso === cursorIso
                            ? 'tabindex="0" data-key="cursor"'
                            : 'tabindex="-1"',
                        iso === todayIso ? 'aria-current="date"' : '',
                        allowed(iso) ? '' : 'aria-disabled="true"',
                    ].join(' ');
                    cells += `<td aria-selected="${iso === input.value}"><button type="button" class="${classes}" data-date="${iso}" aria-label="${dayFormat.format(date)}" ${attributes}>${date.getDate()}</button></td>`;
                }
                rows += `<tr>${cells}</tr>`;
            }

            const head = WEEKDAYS.map((day) => `<th scope="col">${day}</th>`);
            return `<table role="grid" class="mf-calendar__grid" aria-label="${monthFormat.format(cursor)}">
                <thead><tr>${head.join('')}</tr></thead>
                <tbody>${rows}</tbody>
            </table>`;
        }

        function monthsHtml() {
            const year = cursor.getFullYear();
            const buttons = MONTHS.map((name, month) => {
                const first = toIso(new Date(year, month, 1));
                const last = toIso(new Date(year, month + 1, 0));
                const disabled =
                    (input.min && last < input.min) ||
                    (input.max && first > input.max);
                const selected =
                    month === cursor.getMonth() ? ' is-selected' : '';
                return `<button type="button" class="mf-calendar__month${selected}" data-month="${month}"${disabled ? ' disabled' : ''}>${name}</button>`;
            });
            return `<div class="mf-calendar__months">${buttons.join('')}</div>`;
        }

        function footHtml() {
            const todayAllowed = allowed(toIso(today()));
            return `<div class="mf-calendar__foot">
                <button type="button" class="mf-calendar__action" data-action="today"${todayAllowed ? '' : ' disabled'}>Today</button>
                <button type="button" class="mf-calendar__action" data-action="clear"${input.value ? '' : ' disabled'}>Clear</button>
            </div>`;
        }

        // Redraws the open calendar and puts focus back where it was, or on
        // the focused day after keyboard moves.
        function draw() {
            const focused = panel.contains(document.activeElement)
                ? document.activeElement.dataset.key
                : null;
            const key = focusCursor ? 'cursor' : focused;
            focusCursor = false;

            panel.innerHTML =
                headHtml() +
                (view === 'days' ? daysHtml() : monthsHtml()) +
                footHtml();
            if (key) {
                panel.querySelector(`[data-key="${key}"]`)?.focus();
            }
        }

        function pick(iso) {
            if (iso && !allowed(iso)) {
                return;
            }
            close?.();
            trigger.focus();
            if (iso !== input.value) {
                input.value = iso;
                emit(input);
            }
        }

        function onClick(event) {
            const button = event.target.closest('button');
            if (!button || button.disabled) {
                return;
            }
            const { action, date, month, months } = button.dataset;
            if (date) {
                pick(date);
            } else if (month) {
                cursor = addMonths(cursor, Number(month) - cursor.getMonth());
                view = 'days';
                focusCursor = true;
                draw();
            } else if (months) {
                cursor = addMonths(cursor, Number(months));
                draw();
            } else if (action === 'view') {
                view = view === 'days' ? 'months' : 'days';
                draw();
            } else if (action === 'today') {
                pick(toIso(today()));
            } else if (action === 'clear') {
                pick('');
            }
        }

        function onKeyDown(event) {
            // Keeps Nova's keyboard shortcuts and a modal's Escape out of it.
            event.stopPropagation();

            if (event.key === 'Escape') {
                event.preventDefault();
                close?.();
                trigger.focus();
                return;
            }
            if (!event.target.dataset.date) {
                return;
            }
            const moves = {
                ArrowLeft: () => addDays(cursor, -1),
                ArrowRight: () => addDays(cursor, 1),
                ArrowUp: () => addDays(cursor, -7),
                ArrowDown: () => addDays(cursor, 7),
                Home: () => addDays(cursor, -cursor.getDay()),
                End: () => addDays(cursor, 6 - cursor.getDay()),
                PageUp: () => addMonths(cursor, event.shiftKey ? -12 : -1),
                PageDown: () => addMonths(cursor, event.shiftKey ? 12 : 1),
            };
            if (moves[event.key]) {
                event.preventDefault();
                cursor = moves[event.key]();
                focusCursor = true;
                draw();
            }
        }

        function show() {
            let start = parseIso(input.value) ?? today();
            if (input.min && toIso(start) < input.min) {
                start = parseIso(input.min) ?? start;
            }
            if (input.max && toIso(start) > input.max) {
                start = parseIso(input.max) ?? start;
            }
            cursor = start;
            view = 'days';

            panel = document.createElement('div');
            panel.id = nextId();
            panel.className = 'mf-popup mf-calendar';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-label', 'Choose date');
            panel.addEventListener('click', onClick);
            panel.addEventListener('keydown', onKeyDown);
            panel.addEventListener('focusout', (event) => {
                const next = event.relatedTarget;
                if (
                    next &&
                    !event.currentTarget.contains(next) &&
                    next !== trigger
                ) {
                    close?.();
                }
            });

            close = openPopup(trigger, panel, () => {
                panel = null;
                close = null;
            });
            focusCursor = true;
            draw();
        }

        trigger.addEventListener('click', () => (close ? close() : show()));
        trigger.addEventListener('keydown', (event) => {
            if (
                !close &&
                (event.key === 'ArrowDown' || event.key === 'ArrowUp')
            ) {
                event.preventDefault();
                show();
            }
        });
        onAssign(input, ['value'], render);

        return { control: input, render, close: () => close?.(), trigger };
    }

    // --- Wiring ------------------------------------------------------------

    const enhanced = new Set();

    function enhance(control) {
        const item =
            control.tagName === 'SELECT'
                ? enhanceSelect(control)
                : enhanceDate(control);

        // Options, labels, classes and the disabled state can all change.
        item.changes = new MutationObserver(item.render);
        item.changes.observe(control, {
            attributes: true,
            childList: true,
            subtree: true,
            characterData: true,
        });
        item.render();
        enhanced.add(item);
    }

    function scan() {
        for (const item of enhanced) {
            if (!item.control.isConnected) {
                item.changes.disconnect();
                item.close();
                item.trigger.remove();
                enhanced.delete(item);
            }
        }
        document.querySelectorAll(CONTROLS).forEach(enhance);
    }

    new MutationObserver(scan).observe(document.body, {
        childList: true,
        subtree: true,
    });
    scan();
})();
