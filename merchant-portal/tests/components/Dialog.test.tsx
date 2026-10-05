import { fireEvent, render, screen } from '@testing-library/react';
import { Dialog } from '../../src/components/Dialog';

it('closes on a backdrop click but not on a click inside the box', () => {
  const onClose = vi.fn();
  const { container } = render(
    <Dialog title="New charge" open onClose={onClose}>
      <p>Form</p>
    </Dialog>,
  );
  const dialog = container.querySelector('dialog')!;

  fireEvent.mouseDown(screen.getByText('Form'));
  fireEvent.click(screen.getByText('Form'));
  expect(onClose).not.toHaveBeenCalled();

  // Press inside, release on the backdrop: a dragged text selection.
  fireEvent.mouseDown(screen.getByText('Form'));
  fireEvent.click(dialog, { clientX: -10, clientY: -10 });
  expect(onClose).not.toHaveBeenCalled();

  fireEvent.mouseDown(dialog, { clientX: -10, clientY: -10 });
  fireEvent.click(dialog, { clientX: -10, clientY: -10 });
  expect(onClose).toHaveBeenCalledTimes(1);
});

it('labels each dialog with its own title when both are mounted', () => {
  const { container } = render(
    <>
      <Dialog title="New charge" open={false} onClose={() => {}}>
        <p>Form</p>
      </Dialog>
      <Dialog title="Charge details" open onClose={() => {}}>
        <p>Details</p>
      </Dialog>
    </>,
  );

  const dialogs = container.querySelectorAll('dialog');
  expect(dialogs).toHaveLength(2);
  const firstTitleId = dialogs[0]!.getAttribute('aria-labelledby');
  const secondTitleId = dialogs[1]!.getAttribute('aria-labelledby');
  expect(firstTitleId).not.toBe(secondTitleId);
  expect(dialogs[0]!.querySelector('h2')).toHaveAttribute('id', firstTitleId);
  expect(dialogs[1]!.querySelector('h2')).toHaveAttribute('id', secondTitleId);
});
