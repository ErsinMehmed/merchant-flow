/**
 * Open Nova's delete confirmation directly when the menu has one action.
 * Keep the dropdown when restore or force-delete adds other choices.
 */
mfOverrideComponent('DeleteMenu', (DeleteMenu) => ({
    extends: DeleteMenu,

    // Listens on the document because the root element comes and goes with
    // the component's v-if.
    mounted() {
        this.onTrashClick = (event) => this.openOnlyAction(event);
        document.addEventListener('click', this.onTrashClick, true);
        this.markDirect();
    },

    updated() {
        this.markDirect();
    },

    beforeUnmount() {
        document.removeEventListener('click', this.onTrashClick, true);
    },

    methods: {
        onlyAction() {
            const actions = [
                this.shouldShowDeleteItem &&
                    this.confirmDeleteSelectedResources,
                this.shouldShowRestoreItem && this.confirmRestore,
                this.shouldShowForceDeleteItem &&
                    this.confirmForceDeleteSelectedResources,
            ].filter(Boolean);

            return actions.length === 1 ? actions[0] : null;
        },

        trigger() {
            return this.$el.querySelector?.('button') ?? null;
        },

        openOnlyAction(event) {
            const action = this.onlyAction();
            const trigger = event.target.closest('button');

            if (action === null || !trigger || trigger !== this.trigger()) {
                return;
            }

            // Capture phase: stops the dropdown trigger from ever opening.
            event.preventDefault();
            event.stopPropagation();
            action.call(this);
        },

        markDirect() {
            this.trigger()?.toggleAttribute(
                'data-mf-no-menu',
                this.onlyAction() !== null,
            );
        },
    },
}));
