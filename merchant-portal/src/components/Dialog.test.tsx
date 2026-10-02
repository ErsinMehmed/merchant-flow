import { render } from '@testing-library/react';
import { Dialog } from './Dialog';

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
