/**
 * Toggle all rows directly when an index has one page; keep Nova's dropdown
 * when selecting the current page differs from selecting every matching row.
 */
mfOverrideComponent('SelectAllDropdown', (SelectAllDropdown) => ({
    extends: SelectAllDropdown,

    inject: {
        mfSelectedCount: { from: 'selectedResourcesCount', default: 0 },
    },

    mounted() {
        this.onTriggerClick = (event) => this.toggleWithoutMenu(event);
        document.addEventListener('click', this.onTriggerClick, true);
        this.markDirect();
    },

    updated() {
        this.markDirect();
    },

    beforeUnmount() {
        document.removeEventListener('click', this.onTriggerClick, true);
    },

    methods: {
        onePage() {
            return this.allMatchingResourceCount <= this.currentPageCount;
        },

        trigger() {
            return (
                [
                    ...document.querySelectorAll(
                        '[dusk="select-all-dropdown-trigger"]',
                    ),
                ].find((button) => mfOwns(this.$, button)) ?? null
            );
        },

        toggleWithoutMenu(event) {
            const trigger = event.target.closest(
                '[dusk="select-all-dropdown-trigger"]',
            );

            // The "x" next to the selected count keeps its own handler.
            if (
                !this.onePage() ||
                !trigger ||
                !mfOwns(this.$, trigger) ||
                event.target.closest('[dusk="deselect-all-button"]')
            ) {
                return;
            }

            // Capture phase: stops the dropdown trigger from ever opening.
            event.preventDefault();
            event.stopPropagation();
            this.$emit(
                this.mfSelectedCount > 0 ? 'deselect' : 'toggle-select-all',
            );
        },

        markDirect() {
            this.trigger()?.toggleAttribute('data-mf-no-menu', this.onePage());
        },
    },
}));
