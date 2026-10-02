// Relationship tables on detail pages (an agent's merchants, users, ...) get
// no cards, so App\Nova\Cards\InlineFilters cannot reach them and Nova keeps
// their filters in the funnel dropdown. For those tables Nova's FilterMenu is
// replaced with the same filter row the resource indexes show: under the
// table's search box, the same controls and classes (inline-filters.css,
// form-controls.js), and the page size in the pagination bar.
//
// The controls drive Nova's own filter state in the store, exactly as the
// dropdown did, so Nova still writes the `<relationship>_filter` parameter
// and reloads the table. Top-level indexes keep the dropdown hidden behind
// the InlineFilters card.
(() => {
    const { h, Teleport, resolveComponent } = window.Vue;

    mfOverrideComponent('FilterMenu', (FilterMenu) => ({
        extends: FilterMenu,

        data: () => ({ mfHost: null }),

        mounted() {
            if (!this.viaResource) {
                return;
            }

            // The row goes right above the table's card, under the search box.
            this.mfIndex = this.$el.parentElement?.closest(
                '[dusk$="-index-component"]',
            );
            const card = this.mfIndex
                ? [...this.mfIndex.children].find((child) =>
                      child.contains(this.$el),
                  )
                : null;
            if (!card) {
                return;
            }

            const host = document.createElement('div');
            card.before(host);
            this.mfHost = host;

            // Nova re-renders the pagination bar on every load, so the page
            // size control is put back when it goes.
            this.mfObserver = new MutationObserver(() => this.placePerPage());
            this.mfObserver.observe(this.mfIndex, {
                childList: true,
                subtree: true,
            });
            this.placePerPage();
        },

        beforeUnmount() {
            this.mfObserver?.disconnect();
            this.mfPerPage?.remove();
        },

        unmounted() {
            this.mfHost?.remove();
        },

        methods: {
            placePerPage() {
                if (this.perPageOptions.length < 2) {
                    return;
                }
                const bar = this.mfIndex.querySelector('nav')?.parentElement;
                if (!bar || (this.mfPerPage && bar.contains(this.mfPerPage))) {
                    return;
                }
                this.mfPerPage = this.mfPerPage ?? this.perPageControl();
                bar.classList.add('mf-pagination');
                bar.append(this.mfPerPage);
            },

            perPageControl() {
                const label = document.createElement('label');
                label.className = 'mf-per-page';

                const text = document.createElement('span');
                text.textContent = this.__('Per Page');

                const select = document.createElement('select');
                select.className =
                    'form-control form-input form-control-bordered h-8 text-xs';
                for (const option of this.perPageOptions) {
                    const element = new Option(String(option), String(option));
                    element.selected = String(option) === String(this.perPage);
                    select.append(element);
                }
                select.addEventListener('change', () =>
                    this.$emit('per-page-changed', select.value),
                );

                label.append(text, select);
                return label;
            },

            change(filter, value) {
                this.handleFilterChanged({ filterClass: filter.class, value });
            },

            reset() {
                Nova.$emit('clear-filter-values');
                this.$emit('clear-selected-filters');
            },

            // Native controls with Nova's input classes, as in
            // inline-filters.js; form-controls.js styles them.
            control(filter) {
                const attributes = {
                    class: 'form-control form-input form-control-bordered w-full',
                    value: String(filter.currentValue ?? ''),
                    onChange: (event) => this.change(filter, event.target.value),
                };

                if (filter.component === 'date-filter') {
                    return h('input', {
                        ...attributes,
                        type: 'date',
                        placeholder: 'Any date',
                    });
                }

                if (filter.component === 'select-filter') {
                    return h('select', attributes, [
                        h('option', { value: '' }, 'All'),
                        ...filter.options.map((option) =>
                            h('option', { value: option.value }, option.label),
                        ),
                    ]);
                }

                // Any other filter type keeps Nova's own control.
                return h(resolveComponent(filter.component), {
                    filterKey: filter.class,
                    lens: this.lens,
                    resourceName: this.resourceName,
                    onChange: this.handleFilterChanged,
                });
            },
        },

        render(ctx, ...rest) {
            if (!this.viaResource) {
                return FilterMenu.render(ctx, ...rest);
            }

            if (!this.mfHost || this.filters.length === 0) {
                return null;
            }

            return h(Teleport, { to: this.mfHost }, [
                h(
                    'div',
                    {
                        class: 'mf-filters',
                        role: 'search',
                        'aria-label': 'Filters',
                    },
                    [
                        ...this.filters.map((filter) =>
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
                                disabled: !this.filters.some(
                                    (filter) =>
                                        filter.currentValue !== null &&
                                        filter.currentValue !== undefined &&
                                        filter.currentValue !== '',
                                ),
                                onClick: () => this.reset(),
                            },
                            'Reset filters',
                        ),
                    ],
                ),
            ]);
        },
    }));
})();
