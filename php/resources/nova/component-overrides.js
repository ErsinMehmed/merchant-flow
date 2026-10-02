/**
 * Lets the other Nova scripts extend components before Nova registers them.
 * Also exposes a DOM ownership check for document-level event listeners.
 */
(() => {
    const overrides = new Map();

    /**
     * @param {string} name
     * @param {(definition: object) => object} extend
     */
    window.mfOverrideComponent = (name, extend) => overrides.set(name, extend);

    /**
     * @param {object} instance Vue component instance
     * @param {Element | null} element
     * @returns {boolean}
     */
    window.mfOwns = (instance, element) => {
        for (
            let owner = element?.__vueParentComponent;
            owner;
            owner = owner.parent
        ) {
            if (owner === instance) {
                return true;
            }
        }

        return false;
    };

    Nova.booting((app) => {
        const register = app.component.bind(app);

        app.component = (name, definition) =>
            definition && overrides.has(name)
                ? register(name, overrides.get(name)(definition))
                : register(name, definition);
    });
})();
