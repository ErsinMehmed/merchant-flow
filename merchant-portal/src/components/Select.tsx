import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { createPortal } from 'react-dom';
import { useAnchoredPanel } from '../lib/useAnchoredPanel';
import { CheckIcon, ChevronDownIcon } from './icons';

export interface SelectOption {
  value: string;
  label: string;
}

interface Props {
  value: string;
  options: readonly SelectOption[];
  onChange: (value: string) => void;
  /** Shown while no option has the current value. */
  placeholder?: string;
  invalid?: boolean;
}

/**
 * Select-only combobox (WAI-ARIA APG pattern): focus stays on the trigger,
 * arrow keys and typing move through the options, Enter picks one. Placed
 * inside a <label>, which gives it its name.
 */
export function Select({
  value,
  options,
  onChange,
  placeholder = 'Choose…',
  invalid = false,
}: Props) {
  const id = useId();
  const triggerRef = useRef<HTMLButtonElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  // Where the open list is rendered: inside the surrounding modal <dialog>,
  // which keeps it interactive, or the body. Null while closed.
  const [host, setHost] = useState<HTMLElement | null>(null);
  const [active, setActive] = useState(0);
  const typed = useRef({ text: '', at: 0 });

  const open = host !== null;
  const selectedIndex = options.findIndex((option) => option.value === value);
  const selected = options[selectedIndex];
  const listId = `${id}-list`;
  const optionId = (index: number) => `${id}-option-${index}`;

  const close = () => setHost(null);
  useAnchoredPanel(open, triggerRef, listRef, close);

  useEffect(() => {
    if (open) {
      listRef.current?.children[active]?.scrollIntoView?.({ block: 'nearest' });
    }
  }, [open, active]);

  function show() {
    setActive(Math.max(selectedIndex, 0));
    setHost(triggerRef.current?.closest('dialog') ?? document.body);
  }

  function choose(index: number) {
    const option = options[index];
    close();
    triggerRef.current?.focus();
    if (option && option.value !== value) onChange(option.value);
  }

  // Typing jumps to the next option that starts with the typed text.
  function jumpTo(key: string) {
    const now = Date.now();
    const text =
      (now - typed.current.at < 700 ? typed.current.text : '') +
      key.toLowerCase();
    typed.current = { text, at: now };

    const start = text.length === 1 ? active + 1 : active;
    for (let step = 0; step < options.length; step++) {
      const index = (start + step) % options.length;
      if (options[index]?.label.toLowerCase().startsWith(text)) {
        setActive(index);
        return;
      }
    }
  }

  function onKeyDown(event: KeyboardEvent<HTMLButtonElement>) {
    if (!open) {
      if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        show();
      }
      return;
    }

    const last = options.length - 1;
    switch (event.key) {
      case 'ArrowDown':
        setActive((index) => Math.min(index + 1, last));
        break;
      case 'ArrowUp':
        setActive((index) => Math.max(index - 1, 0));
        break;
      case 'Home':
      case 'PageUp':
        setActive(0);
        break;
      case 'End':
      case 'PageDown':
        setActive(last);
        break;
      case 'Enter':
      case ' ':
        choose(active);
        break;
      // Prevented below, so a surrounding <dialog> stays open.
      case 'Escape':
        close();
        break;
      case 'Tab':
        close();
        return;
      default:
        if (event.key.length !== 1 || event.ctrlKey || event.metaKey) return;
        jumpTo(event.key);
    }
    event.preventDefault();
  }

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        role="combobox"
        className="control"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={open ? listId : undefined}
        aria-activedescendant={open ? optionId(active) : undefined}
        aria-invalid={invalid || undefined}
        onClick={() => (open ? close() : show())}
        onKeyDown={onKeyDown}
        // Firefox clicks a button on Space key-up even when key-down was
        // handled; the key-down above already did the work.
        onKeyUp={(event) => {
          if (event.key === ' ') event.preventDefault();
        }}
      >
        <span className={selected ? 'control-value' : 'control-placeholder'}>
          {selected?.label ?? placeholder}
        </span>
        <ChevronDownIcon />
      </button>

      {host &&
        createPortal(
          <ul
            ref={listRef}
            id={listId}
            role="listbox"
            popover="manual"
            className="panel listbox"
            // Keeps focus on the trigger while an option is clicked.
            onMouseDown={(event) => event.preventDefault()}
          >
            {options.map((option, index) => (
              <li
                key={option.value}
                id={optionId(index)}
                role="option"
                aria-selected={index === selectedIndex}
                className={
                  index === active
                    ? 'listbox-option is-active'
                    : 'listbox-option'
                }
                onMouseMove={() => setActive(index)}
                onClick={() => choose(index)}
              >
                <span>{option.label}</span>
                {index === selectedIndex && <CheckIcon />}
              </li>
            ))}
          </ul>,
          host,
        )}
    </>
  );
}
