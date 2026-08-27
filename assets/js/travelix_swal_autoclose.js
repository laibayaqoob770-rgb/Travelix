/**
 * Makes SweetAlert2 "success" popups (the green checkmark) auto-dismiss on
 * their own instead of requiring the user to click OK. Dialogs that offer a
 * real choice (cancel/deny button, or a text input) are left untouched since
 * those need an explicit decision.
 *
 * Also blurs the page behind every SweetAlert2 popup, so focus is pulled
 * onto the dialog instead of just dimming the background.
 *
 * Load this AFTER the sweetalert2 <script> tag on any page.
 */
(function () {
    function injectBackdropBlur() {
        if (document.getElementById('travelix-swal-blur-style')) return;
        const style = document.createElement('style');
        style.id = 'travelix-swal-blur-style';
        style.textContent = '.swal2-container{backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);}';
        document.head.appendChild(style);
    }

    function patchSwal() {
        if (typeof Swal === 'undefined' || Swal.__travelixAutoClosePatched) return;

        const originalFire = Swal.fire.bind(Swal);

        Swal.fire = function (...args) {
            const opts = args[0];
            const isPlainOpts = opts && typeof opts === 'object' && !Array.isArray(opts);

            const eligible = isPlainOpts
                && opts.icon === 'success'
                && !opts.showCancelButton
                && !opts.showDenyButton
                && !opts.input;

            if (!eligible) {
                return originalFire(...args);
            }

            const patchedOpts = Object.assign({
                showConfirmButton: false,
                timer: 1800,
                timerProgressBar: true
            }, opts);

            const result = originalFire(patchedOpts);

            if (result && typeof result.then === 'function') {
                return result.then((r) => {
                    if (r && r.dismiss === Swal.DismissReason.timer) {
                        return Object.assign({}, r, { isConfirmed: true });
                    }
                    return r;
                });
            }

            return result;
        };

        Swal.__travelixAutoClosePatched = true;
    }

    if (typeof Swal !== 'undefined') {
        patchSwal();
    } else {
        document.addEventListener('DOMContentLoaded', patchSwal);
        window.addEventListener('load', patchSwal);
    }

    if (document.head) {
        injectBackdropBlur();
    } else {
        document.addEventListener('DOMContentLoaded', injectBackdropBlur);
    }
})();
