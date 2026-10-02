// Lets the scripts loaded after this one change one of Nova's own Vue
// components: `mfOverrideComponent('DeleteMenu', (DeleteMenu) => ({...}))`.
// Nova runs booting callbacks before it registers its components, so the
// registration is intercepted and the returned definition registered instead.
//
// `mfOwns(instance, element)` tells whether a DOM element was rendered by that
// component instance or one of its children, for listeners that sit on the
// document because the component's root element is not a stable target.
(() => {
    const overrides = new Map();

    window.mfOverrideComponent = (name, extend) => overrides.set(name, extend);

    window.mfOwns = (instance, element) => {
        for (let owner = element?.__vueParentComponent; owner; owner = owner.parent) {
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
