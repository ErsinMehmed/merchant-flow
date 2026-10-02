import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DatePicker } from '../../src/components/DatePicker';

function renderPicker(props: { value?: string; min?: string } = {}) {
  const onChange = vi.fn();
  render(
    <label>
      From
      <DatePicker
        value={props.value ?? ''}
        min={props.min}
        onChange={onChange}
      />
    </label>,
  );
  return { onChange, trigger: screen.getByRole('combobox', { name: 'From' }) };
}

describe('DatePicker', () => {
  it('shows the month of the chosen date and picks a day', async () => {
    const { onChange, trigger } = renderPicker({ value: '2026-10-15' });
    expect(trigger).toHaveTextContent('Oct 15, 2026');

    await userEvent.click(trigger);
    expect(
      screen.getByRole('grid', { name: 'October 2026' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Oct 15, 2026' })).toHaveFocus();

    await userEvent.click(screen.getByRole('button', { name: 'Oct 20, 2026' }));

    expect(onChange).toHaveBeenCalledWith('2026-10-20');
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('moves through days and months with the keyboard', async () => {
    const { onChange, trigger } = renderPicker({ value: '2026-10-31' });

    await userEvent.click(trigger);
    await userEvent.keyboard('{ArrowRight}');
    expect(
      screen.getByRole('grid', { name: 'November 2026' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Nov 1, 2026' })).toHaveFocus();

    await userEvent.keyboard('{PageUp}{Enter}');
    expect(onChange).toHaveBeenCalledWith('2026-10-01');
  });

  it('jumps to another month from the month grid', async () => {
    const { onChange, trigger } = renderPicker({ value: '2026-10-15' });

    await userEvent.click(trigger);
    await userEvent.click(screen.getByRole('button', { name: /choose month/ }));
    await userEvent.click(
      screen.getByRole('button', { name: 'Previous year' }),
    );
    await userEvent.click(screen.getByRole('button', { name: 'Mar' }));
    await userEvent.click(screen.getByRole('button', { name: 'Mar 3, 2025' }));

    expect(onChange).toHaveBeenCalledWith('2025-03-03');
  });

  it('does not pick days before the minimum', async () => {
    const { onChange, trigger } = renderPicker({ min: '2026-10-10' });

    await userEvent.click(trigger);
    const before = screen.getByRole('button', { name: 'Oct 9, 2026' });
    expect(before).toHaveAttribute('aria-disabled', 'true');

    await userEvent.click(before);
    expect(onChange).not.toHaveBeenCalled();
  });

  it('clears the date', async () => {
    const { onChange, trigger } = renderPicker({ value: '2026-10-15' });

    await userEvent.click(trigger);
    await userEvent.click(screen.getByRole('button', { name: 'Clear' }));

    expect(onChange).toHaveBeenCalledWith('');
  });
});
