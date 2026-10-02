import { act, render, waitFor } from '@testing-library/react';
import type { IDatasource, IGetRowsParams } from 'ag-grid-community';
import { api } from '../api/client';
import type { Charge } from '../api/types';
import { ChargesGrid } from './ChargesGrid';

const grid = vi.hoisted(() => ({ datasource: null as IDatasource | null }));

vi.mock('ag-grid-react', () => ({
  AgGridReact: ({ datasource }: { datasource: IDatasource }) => {
    grid.datasource = datasource;
    return null;
  },
}));

vi.mock('../lib/useMediaQuery', () => ({ useMediaQuery: () => false }));

it('ignores a response from the previous filters', async () => {
  let resolveOld!: (value: Awaited<ReturnType<typeof api.charges>>) => void;
  let oldSignal: AbortSignal | undefined;
  vi.spyOn(api, 'charges')
    .mockImplementationOnce(
      (_query, signal) =>
        new Promise((resolve) => {
          oldSignal = signal;
          resolveOld = resolve;
        }),
    )
    .mockResolvedValue({
      charges: [],
      meta: { page: 1, page_size: 25, total: 0 },
    });

  const onLoaded = vi.fn();
  const onError = vi.fn();
  const onSelect = vi.fn();
  const props = {
    showMerchant: false,
    refreshToken: 0,
    onLoaded,
    onError,
    onSelect,
  };
  const { rerender } = render(<ChargesGrid {...props} filters={{}} />);
  const oldRows = rows();

  act(() => grid.datasource!.getRows(oldRows));
  rerender(<ChargesGrid {...props} filters={{ status: 'captured' }} />);

  expect(oldSignal?.aborted).toBe(true);
  await act(async () => {
    resolveOld({
      charges: [{ id: 1 } as Charge],
      meta: { page: 1, page_size: 25, total: 99 },
    });
  });
  expect(oldRows.successCallback).not.toHaveBeenCalled();
  expect(onLoaded).not.toHaveBeenCalled();
  expect(onError).not.toHaveBeenCalled();

  act(() => grid.datasource!.getRows(rows()));
  await waitFor(() => expect(onLoaded).toHaveBeenCalledWith(0));
});

function rows(): IGetRowsParams {
  return {
    startRow: 0,
    endRow: 25,
    sortModel: [],
    successCallback: vi.fn(),
    failCallback: vi.fn(),
  } as unknown as IGetRowsParams;
}
