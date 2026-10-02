/**
 * Replace only Nova's logout confirm() with a styled dialog, then dispatch
 * Nova's usual logout action. Other confirm() calls remain native.
 */
(() => {
    const LOGOUT_PROMPT = 'Are you sure you want to log out?';
    const nativeConfirm = window.confirm.bind(window);

    const translate = (key) => Nova.config('translations')?.[key] || key;

    window.confirm = (message) => {
        if (message !== translate(LOGOUT_PROMPT)) {
            return nativeConfirm(message);
        }

        openLogoutDialog();

        return false;
    };

    function openLogoutDialog() {
        const dialog = document.createElement('dialog');
        dialog.className = 'mf-confirm';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-labelledby', 'mf-confirm-title');
        dialog.setAttribute('aria-describedby', 'mf-confirm-message');
        dialog.innerHTML = `
      <form class="mf-confirm__form">
        <h3 class="mf-confirm__title" id="mf-confirm-title"></h3>
        <p class="mf-confirm__message" id="mf-confirm-message"></p>
        <div class="mf-confirm__footer">
          <button type="button" class="mf-confirm__cancel"></button>
          <button type="submit" class="mf-confirm__submit" autofocus></button>
        </div>
      </form>
    `;

        const form = dialog.querySelector('form');
        const cancelButton = dialog.querySelector('.mf-confirm__cancel');
        const submitButton = dialog.querySelector('.mf-confirm__submit');

        dialog.querySelector('.mf-confirm__title').textContent =
            translate('Logout');
        dialog.querySelector('.mf-confirm__message').textContent =
            translate(LOGOUT_PROMPT);
        cancelButton.textContent = translate('Cancel');
        submitButton.textContent = translate('Logout');

        cancelButton.addEventListener('click', () => dialog.close());

        // The form fills the dialog, so a click on the dialog itself is a click
        // on the backdrop.
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });

        dialog.addEventListener('close', () => {
            dialog.remove();
            Nova.resumeShortcuts();
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            cancelButton.disabled = true;
            submitButton.disabled = true;
            logout();
        });

        document.body.append(dialog);
        Nova.pauseShortcuts();
        dialog.showModal();
    }

    async function logout() {
        try {
            const redirect = await Nova.store.dispatch(
                'logout',
                Nova.config('customLogoutPath'),
            );

            if (redirect !== null) {
                window.location.href = redirect;

                return;
            }

            Nova.redirectToLogin();
        } catch {
            window.location.reload();
        }
    }
})();
