import {
  useEffect,
  useId,
  useRef,
  type MouseEvent,
  type ReactNode,
} from 'react';

interface DialogProps {
  title: string;
  open: boolean;
  onClose: () => void;
  children: ReactNode;
}

/**
 * Native <dialog>: modal focus handling, Escape to close and an inert page
 * behind it come from the browser.
 */
export function Dialog({ title, open, onClose, children }: DialogProps) {
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const pressedBackdrop = useRef(false);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) dialog.showModal();
    if (!open && dialog.open) dialog.close();
  }, [open]);

  return (
    // Only user actions (Escape, the close button, a backdrop click) report a
    // close. The native "close" event also fires when this component closes
    // the dialog itself, e.g. while another dialog is taking over, and must
    // not be forwarded.
    <dialog
      ref={ref}
      className="dialog"
      aria-labelledby={titleId}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
      // Backdrop clicks target the <dialog> itself. Both press and release
      // must land outside the box, so a text selection dragged out of an
      // input does not close the form.
      onMouseDown={(e) => {
        pressedBackdrop.current = isOutside(e);
      }}
      onClick={(e) => {
        if (pressedBackdrop.current && isOutside(e)) onClose();
        pressedBackdrop.current = false;
      }}
    >
      <header className="dialog-header">
        <h2 id={titleId}>{title}</h2>
        <button
          type="button"
          className="icon-button"
          aria-label="Close"
          onClick={onClose}
        >
          ×
        </button>
      </header>
      {open && children}
    </dialog>
  );
}

function isOutside(e: MouseEvent<HTMLDialogElement>): boolean {
  if (e.target !== e.currentTarget) return false;
  const rect = e.currentTarget.getBoundingClientRect();
  return (
    e.clientX < rect.left ||
    e.clientX > rect.right ||
    e.clientY < rect.top ||
    e.clientY > rect.bottom
  );
}
