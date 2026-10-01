/* WISDOM UI: accessible confirmation, success, toast, copy, and mobile-drawer behavior. */
(function () {
    'use strict';

    function toast(message, variant) {
        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        const item = document.createElement('div');
        item.className = 'toast toast--' + (['success', 'error', 'info'].includes(variant) ? variant : 'success');
        const icon = document.createElement('span');
        icon.className = 'toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = variant === 'error' ? '!' : variant === 'info' ? 'i' : '✓';
        const text = document.createElement('span');
        text.className = 'toast__msg';
        text.textContent = String(message);
        item.append(icon, text);
        stack.appendChild(item);
        requestAnimationFrame(() => item.classList.add('is-in'));
        window.setTimeout(() => {
            item.classList.remove('is-in');
            window.setTimeout(() => item.remove(), 400);
        }, 3800);
    }

    function modal(options) {
        const opts = options || {};
        const variant = ['success', 'error', 'confirm'].includes(opts.variant) ? opts.variant : 'success';
        const previousFocus = document.activeElement;
        const backdrop = document.createElement('div');
        backdrop.className = 'backdrop is-open';
        backdrop.setAttribute('aria-hidden', 'true');
        const dialog = document.createElement('section');
        dialog.className = 'modal is-open';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'wisdom-modal-title');
        dialog.setAttribute('aria-describedby', 'wisdom-modal-body');

        const panel = document.createElement('div');
        panel.className = 'modal__panel';
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'modal__close';
        closeButton.setAttribute('aria-label', 'Close dialog');
        closeButton.textContent = '×';
        const icon = document.createElement('div');
        icon.className = 'modal__icon clay ' + (variant === 'success' ? 'clay--teal' : variant === 'error' ? 'clay--gold' : 'clay--navy');
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = variant === 'success' ? '✓' : variant === 'error' ? '!' : '?';
        const title = document.createElement('h2');
        title.className = 'modal__title';
        title.id = 'wisdom-modal-title';
        title.textContent = String(opts.title || '');
        const body = document.createElement('p');
        body.className = 'modal__body';
        body.id = 'wisdom-modal-body';
        body.textContent = String(opts.body || '');
        const actions = document.createElement('div');
        actions.className = 'modal__actions';

        function action(label, className, onClick) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn ' + className;
            button.textContent = label;
            button.addEventListener('click', onClick);
            actions.appendChild(button);
            return button;
        }

        let closeDialog;
        const cancel = variant === 'confirm'
            ? action(String(opts.cancelLabel || 'Cancel'), 'btn--ghost', () => closeDialog())
            : null;
        const confirm = action(
            String((opts.confirm && opts.confirm.label) || 'Continue'),
            variant === 'confirm' ? ((opts.confirm && opts.confirm.danger) ? 'btn--danger' : 'btn--gold') : 'btn--gold',
            () => closeDialog(typeof opts.onConfirm === 'function' ? opts.onConfirm : null)
        );

        panel.append(closeButton, icon, title, body, actions);
        dialog.appendChild(panel);
        document.body.append(backdrop, dialog);
        document.body.classList.add('no-scroll');
        primeWillChange([panel]);

        let closed = false;
        closeDialog = function (afterClose) {
            if (closed) return;
            closed = true;
            dialog.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            document.removeEventListener('keydown', onKeyDown);
            window.setTimeout(() => {
                dialog.remove();
                backdrop.remove();
                document.body.classList.remove('no-scroll');
                if (previousFocus && typeof previousFocus.focus === 'function' && previousFocus.isConnected) previousFocus.focus();
                if (typeof afterClose === 'function') afterClose();
            }, 240);
        };
        function onKeyDown(event) {
            if (event.key === 'Escape') closeDialog();
            if (event.key === 'Tab') {
                const focusable = Array.from(dialog.querySelectorAll('button:not([disabled])'));
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        }
        closeButton.addEventListener('click', () => closeDialog());
        backdrop.addEventListener('click', () => { if (variant !== 'confirm') closeDialog(); });
        document.addEventListener('keydown', onKeyDown);
        (cancel || confirm).focus();
    }

    function wireSuccessModal() {
        const trigger = document.getElementById('js-success-modal');
        if (!trigger) return;
        const payload = {
            title: trigger.dataset.title || 'Saved successfully',
            body: trigger.dataset.body || 'Your changes have been recorded.',
            confirm: trigger.dataset.confirm || 'Continue'
        };
        trigger.remove();
        modal({variant:'success', title:payload.title, body:payload.body, confirm:{label:payload.confirm}});
    }

    function wireConfirmForms() {
        document.querySelectorAll('form[data-confirm-modal]:not([data-confirm-bound])').forEach((form) => {
            form.dataset.confirmBound = '1';
            form.addEventListener('submit', (event) => {
                if (form.dataset.confirmed === '1') return;
                event.preventDefault();
                const submitter = event.submitter || null;
                modal({
                    variant:'confirm',
                    title:form.dataset.modalTitle || 'Are you sure?',
                    body:form.dataset.modalBody || 'Please confirm that you want to continue.',
                    confirm:{label:form.dataset.modalConfirm || 'Continue', danger:form.dataset.modalDanger === '1'},
                    onConfirm:() => {
                        form.dataset.confirmed = '1';
                        if (typeof form.requestSubmit === 'function') {
                            if (submitter && submitter.form === form) form.requestSubmit(submitter);
                            else form.requestSubmit();
                        } else {
                            HTMLFormElement.prototype.submit.call(form);
                        }
                    }
                });
            });
        });
        wireFormLocks();
    }

    function wireCopyButtons() {
        document.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const target = document.getElementById(button.dataset.copy || '');
                if (!target) return;
                try {
                    await navigator.clipboard.writeText(target.textContent.trim());
                    toast('Copied to clipboard', 'success');
                } catch (error) {
                    toast('Copy unavailable. Select and copy the value manually.', 'error');
                }
            });
        });
    }

    function wireDrawer() {
        const rail = document.getElementById('sidebar');
        const hamburger = document.getElementById('hamburger');
        const closeButton = document.getElementById('rail-close');
        if (!rail || !hamburger) return;
        let backdrop = document.getElementById('drawer-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'drawer-backdrop';
            backdrop.className = 'backdrop';
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.appendChild(backdrop);
        }
        const close = () => {
            rail.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            document.body.classList.remove('no-scroll');
            hamburger.classList.remove('is-open');
            hamburger.setAttribute('aria-expanded', 'false');
        };
        hamburger.addEventListener('click', () => {
            const opening = !rail.classList.contains('is-open');
            if (opening) primeWillChange([rail]);
            rail.classList.toggle('is-open', opening);
            backdrop.classList.toggle('is-open', opening);
            document.body.classList.toggle('no-scroll', opening);
            hamburger.classList.toggle('is-open', opening);
            hamburger.setAttribute('aria-expanded', String(opening));
            if (opening && closeButton) closeButton.focus();
        });
        backdrop.addEventListener('click', close);
        if (closeButton) closeButton.addEventListener('click', close);
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
    }

    function wireFormLocks() {
        document.querySelectorAll('form[method="post" i]:not([data-lock-bound])').forEach((form) => {
            form.dataset.lockBound = '1';
            form.addEventListener('submit', (event) => {
                if (form.dataset.submitting === '1') {
                    event.preventDefault();
                    return;
                }
                if (form.hasAttribute('data-confirm-modal') && form.dataset.confirmed !== '1') return;

                form.dataset.submitting = '1';
                const submitter = event.submitter;
                if (submitter instanceof HTMLButtonElement && submitter.name && submitter.value !== '') {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = submitter.name;
                    hidden.value = submitter.value;
                    form.appendChild(hidden);
                }
                form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
                    button.disabled = true;
                    button.dataset.originalText = button.textContent || '';
                    button.textContent = 'Working…';
                    button.classList.add('is-loading');
                });
            });
        });
    }

    function wireScrollbarFade() {
        let timer;
        const show = () => {
            document.documentElement.classList.add('is-scrolling');
            clearTimeout(timer);
            timer = window.setTimeout(() => document.documentElement.classList.remove('is-scrolling'), 850);
        };
        window.addEventListener('scroll', show, {passive:true});
        window.addEventListener('wheel', show, {passive:true});
    }

    function wireProofViewer() {
        document.querySelectorAll('[data-proof-link]').forEach((link) => link.addEventListener('click', (event) => {
            event.preventDefault();
            const viewer = window.open(link.href, '_blank', 'noopener,width=980,height=760');
            if (viewer) viewer.focus();
        }));
    }

    function bootResponsiveTables() {
        document.querySelectorAll('table.table').forEach((table) => {
            const labels = Array.from(table.querySelectorAll('thead th')).map((th) => (th.textContent || '').trim());
            table.querySelectorAll('tbody tr').forEach((row) => row.querySelectorAll('td').forEach((cell, index) => {
                if (!cell.hasAttribute('colspan') && labels[index]) cell.dataset.label = cell.dataset.label || labels[index];
            }));
        });
    }

    function skeleton(target, template, delayMs = 220) {
        let timer = window.setTimeout(() => {
            target.innerHTML = template;
            target.setAttribute('aria-busy', 'true');
        }, delayMs);
        let stopped = false;
        return {
            stop(finalHtml) {
                if (stopped) return;
                stopped = true;
                window.clearTimeout(timer);
                target.removeAttribute('aria-busy');
                if (typeof finalHtml === 'string') target.innerHTML = finalHtml;
            }
        };
    }

    function primeWillChange(elements, duration = 400) {
        elements.filter(Boolean).forEach((element) => {
            element.style.willChange = 'transform, opacity';
            window.setTimeout(() => { element.style.willChange = 'auto'; }, duration);
        });
    }

    function wireProgressiveImages() {
        document.querySelectorAll('img[data-blur]').forEach((image) => {
            const placeholder = image.dataset.blur;
            if (!placeholder) return;
            image.style.backgroundImage = `url("${placeholder.replace(/["\\]/g, '')}")`;
            image.style.backgroundSize = 'cover';
            image.style.filter = 'blur(10px)';
            image.style.opacity = '0.75';
            const finish = () => {
                image.style.filter = 'none';
                image.style.opacity = '1';
                image.style.backgroundImage = 'none';
            };
            if (image.complete && image.naturalWidth > 0) finish();
            else {
                image.addEventListener('load', finish, {once:true});
                image.addEventListener('error', finish, {once:true});
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        wireConfirmForms();
        wireFormLocks();
        wireSuccessModal();
        wireCopyButtons();
        wireDrawer();
        wireProgressiveImages();
        wireScrollbarFade();
        wireProofViewer();
        bootResponsiveTables();
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-submitting="1"]').forEach((form) => {
            form.dataset.submitting = '0';
            form.querySelectorAll('button:disabled').forEach((button) => {
                button.disabled = false;
                button.textContent = button.dataset.originalText || 'Submit';
                button.classList.remove('is-loading');
            });
        });
    });
    window.WisdomUI = {modal:modal, toast:toast, skeleton:skeleton, primeWillChange:primeWillChange, wireConfirmForms:wireConfirmForms};
})();
