import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import {
  addDays,
  addMonths,
  calendarWeeks,
  formatDate,
  isSameDay,
  isWithin,
  parseIsoDate,
  toIsoDate,
  today,
} from '../lib/dates';
import { useAnchoredPanel } from '../lib/useAnchoredPanel';
import { CalendarIcon, ChevronLeftIcon, ChevronRightIcon } from './icons';

interface Props {
  /** "YYYY-MM-DD", or '' for no date. */
  value: string;
  onChange: (value: string) => void;
  min?: string;
  max?: string;
  placeholder?: string;
}

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const MONTHS = Array.from({ length: 12 }, (_, month) =>
  new Date(2000, month, 1).toLocaleString('en-US', { month: 'short' }),
);
const monthTitle = new Intl.DateTimeFormat('en-US', {
  month: 'long',
  year: 'numeric',
});

const cx = (...names: (string | false)[]) => names.filter(Boolean).join(' ');

/**
 * Date field with a calendar popup: arrow keys move by day and week,
 * Page Up/Down by month (with Shift, by year), and the title switches to a
 * month grid for longer jumps. Placed inside a <label>, which names it.
 */
export function DatePicker({
  value,
  onChange,
  min,
  max,
  placeholder = 'Any date',
}: Props) {
  const panelId = useId();
  const triggerRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);
  const [host, setHost] = useState<HTMLElement | null>(null);
  // The focused day; the calendar shows its month.
  const [cursor, setCursor] = useState(today);
  const [view, setView] = useState<'days' | 'months'>('days');
  const focusCursor = useRef(false);

  const open = host !== null;
  const selected = parseIsoDate(value);
  const now = today();

  const close = (refocus: boolean) => {
    setHost(null);
    if (refocus) triggerRef.current?.focus();
  };
  useAnchoredPanel(open, triggerRef, panelRef, () => close(false));

  useEffect(() => {
    if (open && focusCursor.current) {
      focusCursor.current = false;
      panelRef.current?.querySelector<HTMLElement>('[data-cursor]')?.focus();
    }
  }, [open, cursor, view]);

  function show() {
    let start = selected ?? now;
    if (min && toIsoDate(start) < min) start = parseIsoDate(min) ?? start;
    if (max && toIsoDate(start) > max) start = parseIsoDate(max) ?? start;

    setCursor(start);
    setView('days');
    focusCursor.current = true;
    setHost(triggerRef.current?.closest('dialog') ?? document.body);
  }

  function pick(date: Date) {
    const iso = toIsoDate(date);
    if (!isWithin(iso, min, max)) return;
    close(true);
    if (iso !== value) onChange(iso);
  }

  function moveCursor(date: Date) {
    focusCursor.current = true;
    setCursor(date);
  }

  function onGridKeyDown(event: KeyboardEvent) {
    const moves: Record<string, () => Date> = {
      ArrowLeft: () => addDays(cursor, -1),
      ArrowRight: () => addDays(cursor, 1),
      ArrowUp: () => addDays(cursor, -7),
      ArrowDown: () => addDays(cursor, 7),
      Home: () => addDays(cursor, -cursor.getDay()),
      End: () => addDays(cursor, 6 - cursor.getDay()),
      PageUp: () => addMonths(cursor, event.shiftKey ? -12 : -1),
      PageDown: () => addMonths(cursor, event.shiftKey ? 12 : 1),
    };
    const move = moves[event.key];
    if (!move) return;
    event.preventDefault();
    moveCursor(move());
  }

  const step = view === 'days' ? 1 : 12;

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        role="combobox"
        className="control"
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-controls={open ? panelId : undefined}
        onClick={() => (open ? close(false) : show())}
      >
        <span className={selected ? 'control-value' : 'control-placeholder'}>
          {selected ? formatDate(selected) : placeholder}
        </span>
        <CalendarIcon />
      </button>

      {host &&
        createPortal(
          <div
            ref={panelRef}
            id={panelId}
            role="dialog"
            aria-label="Choose date"
            popover="manual"
            className="panel calendar"
            onKeyDown={(event) => {
              // Prevented, so a surrounding <dialog> stays open.
              if (event.key !== 'Escape') return;
              event.preventDefault();
              close(true);
            }}
            onBlur={(event) => {
              const next = event.relatedTarget;
              if (
                next &&
                !panelRef.current?.contains(next) &&
                next !== triggerRef.current
              ) {
                close(false);
              }
            }}
          >
            <div className="calendar-head">
              <button
                type="button"
                className="calendar-nav"
                aria-label={
                  view === 'days' ? 'Previous month' : 'Previous year'
                }
                onClick={() => setCursor(addMonths(cursor, -step))}
              >
                <ChevronLeftIcon />
              </button>
              <button
                type="button"
                className="calendar-title"
                aria-label={
                  view === 'days'
                    ? `${monthTitle.format(cursor)}, choose month`
                    : `${cursor.getFullYear()}, back to days`
                }
                onClick={() => setView(view === 'days' ? 'months' : 'days')}
              >
                {view === 'days'
                  ? monthTitle.format(cursor)
                  : cursor.getFullYear()}
              </button>
              <button
                type="button"
                className="calendar-nav"
                aria-label={view === 'days' ? 'Next month' : 'Next year'}
                onClick={() => setCursor(addMonths(cursor, step))}
              >
                <ChevronRightIcon />
              </button>
            </div>

            {view === 'days' ? (
              <table
                role="grid"
                className="calendar-grid"
                aria-label={monthTitle.format(cursor)}
                onKeyDown={onGridKeyDown}
              >
                <thead>
                  <tr>
                    {WEEKDAYS.map((day) => (
                      <th key={day} scope="col">
                        {day}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {calendarWeeks(cursor).map((week) => (
                    <tr key={toIsoDate(week[0]!)}>
                      {week.map((day) => {
                        const iso = toIsoDate(day);
                        const isCursor = isSameDay(day, cursor);
                        const isSelected = iso === value;
                        const disabled = !isWithin(iso, min, max);
                        return (
                          <td key={iso} aria-selected={isSelected}>
                            <button
                              type="button"
                              tabIndex={isCursor ? 0 : -1}
                              data-cursor={isCursor || undefined}
                              className={cx(
                                'calendar-day',
                                day.getMonth() !== cursor.getMonth() &&
                                  'is-outside',
                                isSameDay(day, now) && 'is-today',
                                isSelected && 'is-selected',
                              )}
                              aria-label={formatDate(day)}
                              aria-current={
                                isSameDay(day, now) ? 'date' : undefined
                              }
                              aria-disabled={disabled || undefined}
                              onClick={() => pick(day)}
                            >
                              {day.getDate()}
                            </button>
                          </td>
                        );
                      })}
                    </tr>
                  ))}
                </tbody>
              </table>
            ) : (
              <div className="calendar-months">
                {MONTHS.map((label, month) => {
                  const year = cursor.getFullYear();
                  const first = toIsoDate(new Date(year, month, 1));
                  const last = toIsoDate(new Date(year, month + 1, 0));
                  return (
                    <button
                      key={label}
                      type="button"
                      className={cx(
                        'calendar-month',
                        month === cursor.getMonth() && 'is-selected',
                      )}
                      disabled={(!!min && last < min) || (!!max && first > max)}
                      onClick={() => {
                        moveCursor(
                          addMonths(cursor, month - cursor.getMonth()),
                        );
                        setView('days');
                      }}
                    >
                      {label}
                    </button>
                  );
                })}
              </div>
            )}

            <div className="calendar-foot">
              <button
                type="button"
                className="calendar-action"
                disabled={!isWithin(toIsoDate(now), min, max)}
                onClick={() => pick(now)}
              >
                Today
              </button>
              <button
                type="button"
                className="calendar-action"
                disabled={!value}
                onClick={() => {
                  close(true);
                  onChange('');
                }}
              >
                Clear
              </button>
            </div>
          </div>,
          host,
        )}
    </>
  );
}
