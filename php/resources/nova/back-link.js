/**
 * A link back to the resource's list on every detail page. When the user came
 * from that list, it returns to the same filters, search and page.
 */
(() => {
    const { h, resolveComponent } = window.Vue;

    // Index URLs as last seen, by resource name. Nova keeps filters in the
    // query string and updates it without a page visit, so the URL is read
    // from the address bar when the user leaves the list.
    const lastIndexUrl = new Map();
    let currentPage = null;

    Nova.$router.on('navigate', (event) => {
        currentPage = event.detail.page;
    });

    Nova.$router.on('before', () => {
        if (currentPage?.component === 'Nova.Index') {
            lastIndexUrl.set(
                currentPage.props.resourceName,
                window.location.pathname + window.location.search,
            );
        }
    });

    const arrow = () =>
        h(
            'svg',
            {
                xmlns: 'http://www.w3.org/2000/svg',
                viewBox: '0 0 20 20',
                fill: 'currentColor',
                'aria-hidden': 'true',
            },
            h('path', {
                'fill-rule': 'evenodd',
                'clip-rule': 'evenodd',
                d: 'M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z',
            }),
        );

    mfOverrideComponent('ResourceDetail', (ResourceDetail) => ({
        ...ResourceDetail,

        render(ctx, ...rest) {
            const detail = ResourceDetail.render(ctx, ...rest);

            // Only the page itself: has-one fields embed ResourceDetail too.
            if (!ctx.shouldOverrideMeta || !ctx.resourceInformation) {
                return detail;
            }

            const href =
                lastIndexUrl.get(ctx.resourceName) ??
                ctx.$url(`/resources/${ctx.resourceName}`);

            return h('div', [
                h(
                    resolveComponent('Link'),
                    {
                        href,
                        class: 'mf-back-link link-default',
                        dusk: 'back-to-index',
                    },
                    () => [
                        arrow(),
                        ctx.__('Back to :resource', {
                            resource: ctx.resourceInformation.label,
                        }),
                    ],
                ),
                detail,
            ]);
        },
    }));
})();
