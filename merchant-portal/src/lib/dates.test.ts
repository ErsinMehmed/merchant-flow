import {
  addMonths,
  calendarWeeks,
  isWithin,
  parseIsoDate,
  toIsoDate,
} from './dates';

describe('dates', () => {
  it('round-trips ISO dates and rejects invalid ones', () => {
    expect(toIsoDate(parseIsoDate('2026-02-28')!)).toBe('2026-02-28');
    expect(parseIsoDate('2026-02-30')).toBeNull();
    expect(parseIsoDate('10/01/2026')).toBeNull();
    expect(parseIsoDate('')).toBeNull();
  });

  it('keeps the day of the month where it can', () => {
    expect(toIsoDate(addMonths(parseIsoDate('2026-01-31')!, 1))).toBe(
      '2026-02-28',
    );
    expect(toIsoDate(addMonths(parseIsoDate('2026-03-15')!, -12))).toBe(
      '2025-03-15',
    );
  });

  it('lays a month out in six weeks starting on Sunday', () => {
    const weeks = calendarWeeks(parseIsoDate('2026-10-15')!);

    expect(weeks).toHaveLength(6);
    expect(toIsoDate(weeks[0]![0]!)).toBe('2026-09-27');
    expect(toIsoDate(weeks[5]![6]!)).toBe('2026-11-07');
  });

  it('checks optional bounds', () => {
    expect(isWithin('2026-10-01')).toBe(true);
    expect(isWithin('2026-10-01', '2026-10-01', '2026-10-01')).toBe(true);
    expect(isWithin('2026-09-30', '2026-10-01')).toBe(false);
    expect(isWithin('2026-10-02', undefined, '2026-10-01')).toBe(false);
  });
});
