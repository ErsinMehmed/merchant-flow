// Renders App\Nova\Cards\InlineFilters. Nova keeps filters and the page size
// in a dropdown behind a button; this shows the filters as a row under the
// search box and the page size in the pagination bar, and hides the dropdown
// (inline-filters.css).
//
// It reads and writes the URL parameters Nova itself uses
// (`<resource>_filter`: base64 JSON of [{class, value}], `<resource>_per_page`),
// so the server-side filter classes still do the filtering and links stay
// shareable. Nova offers no slot for this, so the two pieces are placed with
// plain DOM calls; if the markup they look for is missing, the filters simply
// stay where Nova renders cards.
(() => {
    const { h } = window.Vue;

    const params = () => new URLSearchParams(window.location.search);

    function readFilters(resource) {
        const raw = params().get(`${resource}_filter`);
        if (!raw) {
            return {};
        }
        try {
            return Object.fromEntries(
                JSON.parse(atob(raw)).map((filter) => [
                    filter.class,
                    filter.value,
                ]),
            );
        } catch {
            return {};
        }
    }

    const isSet = (value) =>
        value !== undefined && value !== null && value !== '';

    // Any change starts again from the first page but keeps the sort order.
    function visit(resource, changes) {
        const query = params();
        query.delete(`${resource}_page`);
        for (const [key, value] of Object.entries(changes)) {
            if (isSet(value)) {
                query.set(key, value);
            } else {
                query.delete(key);
            }
        }
        const search = query.toString();
        Nova.visit(`/resources/${resource}${search ? `?${search}` : ''}`);
    }

    function applyFilters(card, values) {
        const active = card.filters
            .filter((filter) => isSet(values[filter.key]))
            .map((filter) => ({
                class: filter.key,
                value: values[filter.key],
            }));

        visit(card.resource, {
            [`${card.resource}_filter`]:
                active.length > 0 ? btoa(JSON.stringify(active)) : null,
        });
    }

    function perPageControl(card) {
        const label = document.createElement('label');
        label.className = 'mf-per-page';

        const text = document.createElement('span');
        text.textContent = 'Per page';

        const select = document.createElement('select');
        const current =
            params().get(`${card.resource}_per_page`) ??
            String(card.perPageOptions[0]);
        for (const option of card.perPageOptions) {
            const element = new Option(String(option), String(option));
            element.selected = String(option) === current;
            select.append(element);
        }
        select.addEventListener('change', () =>
            visit(card.resource, {
                [`${card.resource}_per_page`]: select.value,
            }),
        );

        label.append(text, select);
        return label;
    }

    Nova.booting((app) => {
        app.component('inline-filters', {
            props: ['card'],

            data() {
                return { values: readFilters(this.card.resource) };
            },

            computed: {
                hasActiveFilters() {
                    return Object.values(this.values).some(isSet);
                },
            },

            mounted() {
                this.index = this.$el.closest('[dusk$="-index-component"]');
                this.placeUnderSearch();

                // Nova re-renders the table (and its pagination bar) on every
                // load, so the page size control is put back when it goes.
                this.observer = new MutationObserver(() => this.placePerPage());
                if (this.index) {
                    this.observer.observe(this.index, {
                        childList: true,
                        subtree: true,
                    });
                }
                this.placePerPage();

                // Query-string-only navigation (back button) keeps this
                // component alive, so re-read the filters.
                this.sync = () => {
                    this.values = readFilters(this.card.resource);
                    this.perPage?.remove();
                    this.perPage = null;
                    this.placePerPage();
                };
                document.addEventListener('inertia:navigate', this.sync);
            },

            beforeUnmount() {
                this.observer?.disconnect();
                this.perPage?.remove();
                document.removeEventListener('inertia:navigate', this.sync);
            },

            methods: {
                placeUnderSearch() {
                    if (!this.index) {
                        return;
                    }
                    const searchRow = [...this.index.children].find((child) =>
                        child.querySelector('[dusk="search-input"]'),
                    );
                    if (!searchRow) {
                        return;
                    }
                    const cards = this.$el.parentElement;
                    searchRow.after(this.$el);
                    // Hide Nova's now empty card grid so it leaves no gap.
                    if (cards && cards.children.length === 0) {
                        cards.parentElement.hidden = true;
                    }
                },

                placePerPage() {
                    if (!this.index || this.card.perPageOptions.length < 2) {
                        return;
                    }
                    const bar = this.index.querySelector('nav')?.parentElement;
                    if (!bar || (this.perPage && bar.contains(this.perPage))) {
                        return;
                    }
                    this.perPage = this.perPage ?? perPageControl(this.card);
                    bar.classList.add('mf-pagination');
                    bar.append(this.perPage);
                },

                change(key, value) {
                    this.values = { ...this.values, [key]: value };
                    applyFilters(this.card, this.values);
                },

                reset() {
                    this.values = {};
                    applyFilters(this.card, {});
                },

                control(filter) {
                    const value = this.values[filter.key] ?? '';
                    const onChange = (event) =>
                        this.change(filter.key, event.target.value);

                    if (filter.type === 'date') {
                        return h('input', {
                            type: 'date',
                            class: 'mf-filters__input',
                            value,
                            onChange,
                        });
                    }

                    return h(
                        'select',
                        { class: 'mf-filters__input', onChange },
                        [
                            h(
                                'option',
                                { value: '', selected: value === '' },
                                'All',
                            ),
                            ...filter.options.map((option) =>
                                h(
                                    'option',
                                    {
                                        value: option.value,
                                        selected:
                                            String(value) === option.value,
                                    },
                                    option.label,
                                ),
                            ),
                        ],
                    );
                },
            },

            render() {
                // Resources without filters still get the page size control,
                // but no empty row.
                if (this.card.filters.length === 0) {
                    return h('div', { class: 'mf-filters mf-filters--empty' });
                }

                return h(
                    'div',
                    {
                        class: 'mf-filters',
                        role: 'search',
                        'aria-label': 'Filters',
                    },
                    [
                        ...this.card.filters.map((filter) =>
                            h('label', { class: 'mf-filters__field' }, [
                                h(
                                    'span',
                                    { class: 'mf-filters__label' },
                                    filter.name,
                                ),
                                this.control(filter),
                            ]),
                        ),
                        h(
                            'button',
                            {
                                type: 'button',
                                class: 'mf-filters__reset',
                                disabled: !this.hasActiveFilters,
                                onClick: () => this.reset(),
                            },
                            'Reset filters',
                        ),
                    ],
                );
            },
        });
    });
})();
