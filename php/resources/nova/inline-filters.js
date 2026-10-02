/**
 * Show Nova's index filters beneath search while retaining its URL format:
 * `<resource>_filter` (base64 JSON) and `<resource>_per_page`.
 * If the expected markup is absent, Nova keeps its normal card placement.
 *
 * Changes go through the index component the way Nova's own filter menu
 * does, so only the table reloads. A full visit is the fallback.
 */
(() => {
    const { h } = window.Vue;

    const params = () => new URLSearchParams(window.location.search);

    /**
     * Decode the URL payload, treating malformed filter state as empty.
     * Accepts both `{class, value}` entries and Nova's own `{[class]: value}`.
     * @param {string} resource
     * @returns {Record<string, unknown>}
     */
    function readFilters(resource) {
        const raw = params().get(`${resource}_filter`);
        if (!raw) {
            return {};
        }
        try {
            return Object.fromEntries(
                JSON.parse(atob(raw)).flatMap((filter) =>
                    'class' in filter && 'value' in filter
                        ? [[filter.class, filter.value]]
                        : Object.entries(filter),
                ),
            );
        } catch {
            return {};
        }
    }

    const isSet = (value) =>
        value !== undefined && value !== null && value !== '';

    /** Reset pagination while preserving sort and unrelated query parameters. */
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

    /**
     * The Nova index component this card is rendered in, if any.
     * @param {object} instance
     * @param {string} resource
     */
    function findIndex(instance, resource) {
        for (let owner = instance.$parent; owner; owner = owner.$parent) {
            if (
                owner.resourceName === resource &&
                typeof owner.filterChanged === 'function' &&
                typeof owner.clearSelectedFilters === 'function'
            ) {
                return owner;
            }
        }

        return null;
    }

    function perPageControl(card, onChange) {
        const label = document.createElement('label');
        label.className = 'mf-per-page';

        const text = document.createElement('span');
        text.textContent = 'Per page';

        const select = document.createElement('select');
        select.className =
            'form-control form-input form-control-bordered h-8 text-xs';
        const current =
            params().get(`${card.resource}_per_page`) ??
            String(card.perPageOptions[0]);
        for (const option of card.perPageOptions) {
            const element = new Option(String(option), String(option));
            element.selected = String(option) === current;
            select.append(element);
        }
        select.addEventListener('change', () => onChange(select.value));

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
                this.resourceIndex = findIndex(this, this.card.resource);
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
                    this.perPage =
                        this.perPage ??
                        perPageControl(this.card, (value) =>
                            this.changePerPage(value),
                        );
                    bar.classList.add('mf-pagination');
                    bar.append(this.perPage);
                },

                change(key, value) {
                    this.values = { ...this.values, [key]: value };

                    const index = this.resourceIndex;
                    if (!index) {
                        applyFilters(this.card, this.values);
                        return;
                    }
                    // Same as Nova's filter menu: the index watches the store,
                    // reloads the table and writes the URL (back to page 1).
                    index.$store.commit(
                        `${this.card.resource}/updateFilterState`,
                        { filterClass: key, value },
                    );
                    index.filterChanged();
                },

                reset() {
                    this.values = {};

                    if (!this.resourceIndex) {
                        applyFilters(this.card, {});
                        return;
                    }
                    this.resourceIndex.clearSelectedFilters(
                        this.resourceIndex.lens || null,
                    );
                },

                changePerPage(value) {
                    const index = this.resourceIndex;
                    if (!index) {
                        visit(this.card.resource, {
                            [`${this.card.resource}_per_page`]: value,
                        });
                        return;
                    }
                    index.perPage = value;
                    index.pushAfterUpdatingQueryString({
                        [index.pageParameter]: 1,
                        [index.perPageParameter]: value,
                    });
                },

                // Native controls with Nova's input classes; form-controls.js
                // puts the styled list and calendar in front of them. The
                // value is bound on the control itself, which that script
                // watches.
                control(filter) {
                    const value = String(this.values[filter.key] ?? '');
                    const attributes = {
                        class: 'form-control form-input form-control-bordered w-full',
                        value,
                        onChange: (event) =>
                            this.change(filter.key, event.target.value),
                    };

                    if (filter.type === 'date') {
                        return h('input', {
                            ...attributes,
                            type: 'date',
                            placeholder: 'Any date',
                        });
                    }

                    return h('select', attributes, [
                        h('option', { value: '' }, 'All'),
                        ...filter.options.map((option) =>
                            h('option', { value: option.value }, option.label),
                        ),
                    ]);
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
