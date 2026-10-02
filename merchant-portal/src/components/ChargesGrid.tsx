import {
  themeQuartz,
  type CellKeyDownEvent,
  type ColDef,
  type GridApi,
  type ICellRendererParams,
  type IDatasource,
  type ValueFormatterParams,
} from 'ag-grid-community';
import { AgGridReact } from 'ag-grid-react';
import { useEffect, useMemo, useRef } from 'react';
import { api } from '../api/client';
import type { Charge, ChargeQuery } from '../api/types';
import { formatMoney } from '../lib/money';
import { useMediaQuery } from '../lib/useMediaQuery';
import { StatusBadge } from './StatusBadge';

export const PAGE_SIZE = 25;

export type ChargeFilters = Omit<ChargeQuery, 'page' | 'pageSize' | 'sort'>;

interface Props {
  filters: ChargeFilters;
  showMerchant: boolean;
  /** Bump to reload the current page after a change, keeping the position. */
  refreshToken: number;
  onSelect: (charge: Charge) => void;
  onLoaded: (total: number) => void;
  onError: (message: string) => void;
}

const theme = themeQuartz.withParams({
  accentColor: '#1f4fd8',
  borderRadius: 6,
  fontFamily: 'inherit',
  headerFontWeight: 600,
  headerBackgroundColor: '#f6f7f9',
  rowHoverColor: '#f2f5ff',
});

const dateTime = new Intl.DateTimeFormat(undefined, {
  dateStyle: 'medium',
  timeStyle: 'short',
});
const dateOnly = new Intl.DateTimeFormat(undefined, { dateStyle: 'short' });

function money(field: 'amount' | 'amount_refunded') {
  return ({ data }: ValueFormatterParams<Charge>) => {
    if (!data) return '';
    if (field === 'amount_refunded' && data.amount_refunded === 0) return '—';
    return formatMoney(data[field], data.currency);
  };
}

function StatusCell({ data }: ICellRendererParams<Charge>) {
  return data ? (
    <StatusBadge
      status={data.status}
      partiallyRefunded={data.amount_refunded > 0}
    />
  ) : null;
}

/**
 * Server-side paging and sorting through the AG Grid Community infinite row
 * model: the grid asks for one page at a time and the Go API does the work,
 * so the volume of charges does not matter to the browser.
 */
export function ChargesGrid({
  filters,
  showMerchant,
  refreshToken,
  onSelect,
  onLoaded,
  onError,
}: Props) {
  const gridApi = useRef<GridApi<Charge> | null>(null);
  const narrow = useMediaQuery('(max-width: 720px)');

  // Latest callbacks without rebuilding the datasource on every render.
  const callbacks = useRef({ onLoaded, onError });
  useEffect(() => {
    callbacks.current = { onLoaded, onError };
  });

  const columnDefs = useMemo<ColDef<Charge>[]>(
    () => [
      {
        colId: 'created_at',
        headerName: narrow ? 'Date' : 'Created',
        field: 'created_at',
        sort: 'desc',
        width: narrow ? 104 : 195,
        valueFormatter: ({ value }) =>
          value ? (narrow ? dateOnly : dateTime).format(new Date(value)) : '',
      },
      {
        colId: 'account',
        headerName: 'Merchant',
        field: 'account_name',
        hide: !showMerchant || narrow,
        minWidth: 170,
        flex: 1,
      },
      // On a phone the row shows date, amount and status; the rest is one tap
      // away in the charge details.
      {
        colId: 'customer_email',
        headerName: 'Customer',
        field: 'customer_email',
        minWidth: 160,
        flex: 1.2,
        hide: narrow,
      },
      {
        colId: 'amount',
        headerName: 'Amount',
        field: 'amount',
        type: 'rightAligned',
        width: narrow ? 110 : 125,
        valueFormatter: money('amount'),
      },
      {
        colId: 'amount_refunded',
        headerName: 'Refunded',
        field: 'amount_refunded',
        type: 'rightAligned',
        width: 120,
        sortable: false,
        hide: narrow,
        valueFormatter: money('amount_refunded'),
      },
      {
        colId: 'status',
        headerName: 'Status',
        field: 'status',
        ...(narrow ? { flex: 1, minWidth: 120 } : { width: 155 }),
        cellRenderer: StatusCell,
      },
      {
        colId: 'external_transaction_id',
        headerName: 'Transaction ID',
        field: 'external_transaction_id',
        sortable: false,
        hide: narrow,
        minWidth: 220,
        flex: 1,
        cellClass: 'mono',
      },
    ],
    [narrow, showMerchant],
  );

  // A new datasource (new filters) makes the grid start again from page one.
  const datasource = useMemo<IDatasource>(() => {
    const pending = new Set<AbortController>();

    return {
      getRows: (params) => {
        const controller = new AbortController();
        pending.add(controller);
        const sort = params.sortModel[0];
        const query: ChargeQuery = {
          ...filters,
          page: Math.floor(params.startRow / PAGE_SIZE) + 1,
          pageSize: PAGE_SIZE,
          sort: sort
            ? `${sort.sort === 'desc' ? '-' : ''}${sort.colId}`
            : undefined,
        };
        api
          .charges(query, controller.signal)
          .then(({ charges, meta }) => {
            if (controller.signal.aborted) return;
            params.successCallback(charges, meta.total);
            gridApi.current?.setGridOption(
              'activeOverlay',
              meta.total === 0 ? 'agNoRowsOverlay' : undefined,
            );
            callbacks.current.onLoaded(meta.total);
          })
          .catch((error: unknown) => {
            if (controller.signal.aborted) return;
            params.failCallback();
            callbacks.current.onError(
              error instanceof Error
                ? error.message
                : 'Could not load charges.',
            );
          })
          .finally(() => pending.delete(controller));
      },
      destroy: () => {
        pending.forEach((controller) => controller.abort());
        pending.clear();
      },
    };
  }, [filters]);

  useEffect(() => () => datasource.destroy?.(), [datasource]);

  useEffect(() => {
    if (refreshToken > 0) gridApi.current?.refreshInfiniteCache();
  }, [refreshToken]);

  return (
    <div className="grid-wrap">
      <AgGridReact<Charge>
        theme={theme}
        columnDefs={columnDefs}
        defaultColDef={{
          sortable: true,
          resizable: true,
          suppressMovable: true,
        }}
        rowModelType="infinite"
        datasource={datasource}
        cacheBlockSize={PAGE_SIZE}
        maxBlocksInCache={10}
        pagination
        paginationPageSize={PAGE_SIZE}
        paginationPageSizeSelector={false}
        sortingOrder={['desc', 'asc']}
        // The API sorts by one field (plus the ID as a tie-breaker).
        suppressMultiSort
        getRowId={({ data }) => String(data.id)}
        rowClass="clickable-row"
        overlayNoRowsTemplate="<span class='grid-empty'>No charges match these filters.</span>"
        onGridReady={({ api: readyApi }) => {
          gridApi.current = readyApi;
        }}
        onRowClicked={({ data }) => data && onSelect(data)}
        onCellKeyDown={(event: CellKeyDownEvent<Charge>) => {
          const key = (event.event as KeyboardEvent | undefined)?.key;
          if (key === 'Enter' && event.data) onSelect(event.data);
        }}
      />
    </div>
  );
}
