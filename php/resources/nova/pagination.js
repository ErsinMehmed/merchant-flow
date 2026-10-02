/** Hide Nova's pagination bar when there is no previous or next page. */
mfOverrideComponent('ResourcePagination', (ResourcePagination) => ({
    ...ResourcePagination,

    render(ctx, ...rest) {
        if (!ctx.hasNextPage && !ctx.hasPreviousPage) {
            return null;
        }

        return ResourcePagination.render(ctx, ...rest);
    },
}));
