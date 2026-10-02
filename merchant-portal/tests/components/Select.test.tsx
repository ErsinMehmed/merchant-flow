import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Select } from '../../src/components/Select';

const options = [
  { value: '', label: 'All charges' },
  { value: 'true', label: 'Disputed only' },
  { value: 'false', label: 'Not disputed' },
];

function renderSelect(value = '') {
  const onChange = vi.fn();
  render(
    <label>
      Disputes
      <Select value={value} options={options} onChange={onChange} />
    </label>,
  );
  return {
    onChange,
    trigger: screen.getByRole('combobox', { name: 'Disputes' }),
  };
}

describe('Select', () => {
  it('shows the chosen option and picks another with the mouse', async () => {
    const { onChange, trigger } = renderSelect('false');
    expect(trigger).toHaveTextContent('Not disputed');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();

    await userEvent.click(trigger);
    expect(trigger).toHaveAttribute('aria-expanded', 'true');
    expect(
      screen.getByRole('option', { name: 'Not disputed' }),
    ).toHaveAttribute('aria-selected', 'true');

    await userEvent.click(
      screen.getByRole('option', { name: 'Disputed only' }),
    );

    expect(onChange).toHaveBeenCalledWith('true');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('is driven by the keyboard', async () => {
    const { onChange, trigger } = renderSelect();
    trigger.focus();

    await userEvent.keyboard('{ArrowDown}');
    expect(trigger).toHaveAttribute(
      'aria-activedescendant',
      screen.getByRole('option', { name: 'All charges' }).id,
    );

    await userEvent.keyboard('{ArrowDown}{ArrowDown}{Enter}');
    expect(onChange).toHaveBeenCalledWith('false');
  });

  it('jumps to an option by typing and closes on Escape without a change', async () => {
    const { onChange, trigger } = renderSelect();
    trigger.focus();

    await userEvent.keyboard('{Enter}n');
    expect(trigger).toHaveAttribute(
      'aria-activedescendant',
      screen.getByRole('option', { name: 'Not disputed' }).id,
    );

    await userEvent.keyboard('{Escape}');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
  });
});
