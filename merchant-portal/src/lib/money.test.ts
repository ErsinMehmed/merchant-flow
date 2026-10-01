import { formatMoney, parseAmount, toInputAmount } from './money';

describe('parseAmount', () => {
  it.each([
    ['12', 1200],
    ['12.5', 1250],
    ['12.50', 1250],
    ['0.01', 1],
    ['0.29', 29],
    [' 7.25 ', 725],
    ['999999.99', 99_999_999],
  ])('converts %s to %d minor units', (input, expected) => {
    expect(parseAmount(input)).toBe(expected);
  });

  it.each([
    '',
    '0',
    '0.00',
    '-1',
    '1.005',
    '1,000',
    '1e3',
    'abc',
    '1000000',
    '.5',
  ])('rejects %j', (input) => {
    expect(parseAmount(input)).toBeNull();
  });
});

describe('formatMoney', () => {
  it('formats minor units with the currency symbol', () => {
    expect(formatMoney(123_450, 'USD')).toBe('$1,234.50');
    expect(formatMoney(99, 'EUR')).toBe('€0.99');
    expect(formatMoney(5, 'GBP')).toBe('£0.05');
  });
});

describe('toInputAmount', () => {
  it('renders minor units for an input field', () => {
    expect(toInputAmount(1250)).toBe('12.50');
    expect(toInputAmount(7)).toBe('0.07');
    expect(toInputAmount(99_999_999)).toBe('999999.99');
  });
});
