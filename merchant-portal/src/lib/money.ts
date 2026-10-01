/**
 * All supported currencies (USD, EUR, GBP, CAD) have two decimals, so one
 * minor unit is a hundredth. Amounts are converted with string arithmetic,
 * never floating point.
 */
export const CURRENCIES = ['USD', 'EUR', 'GBP', 'CAD'] as const;

export const MAX_AMOUNT = 99_999_999;

const formatters = new Map<string, Intl.NumberFormat>();

export function formatMoney(minorUnits: number, currency: string): string {
  let formatter = formatters.get(currency);
  if (!formatter) {
    formatter = new Intl.NumberFormat('en-US', { style: 'currency', currency });
    formatters.set(currency, formatter);
  }
  return formatter.format(minorUnits / 100);
}

/** "12.5" -> 1250. Returns null for anything that is not a valid amount. */
export function parseAmount(input: string): number | null {
  const match = /^(\d{1,6})(?:\.(\d{1,2}))?$/.exec(input.trim());
  if (!match) return null;

  const [, whole = '0', fraction = ''] = match;
  const minor = Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
  return minor >= 1 && minor <= MAX_AMOUNT ? minor : null;
}

/** 1250 -> "12.50", for prefilling inputs. */
export function toInputAmount(minorUnits: number): string {
  const whole = Math.floor(minorUnits / 100);
  const cents = String(minorUnits % 100).padStart(2, '0');
  return `${whole}.${cents}`;
}
