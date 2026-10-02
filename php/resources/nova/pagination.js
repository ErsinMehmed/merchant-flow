// Nova draws the pagination bar under every index table that has rows, even
// when everything fits on one page and all its buttons are disabled. This
// leaves the bar out until there is a previous or a next page to go to. The
// page size control lives in that bar and comes back with it.
mfOverrideComponent('ResourcePagination', (ResourcePagination) => ({
    ...ResourcePagination,

    render(ctx, ...rest) {
        if (!ctx.hasNextPage && !ctx.hasPreviousPage) {
            return null;
        }

        return ResourcePagination.render(ctx, ...rest);
    },
}));
