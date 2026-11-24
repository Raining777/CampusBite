'use strict';

/* ------------------------------------------------------------
   Confirm dialogs for destructive actions. Registered before
   the double-submit guard: a cancelled dialog calls
   preventDefault(), which the guard then sees and skips.
   ------------------------------------------------------------ */
for (const form of document.querySelectorAll('[data-confirm]')) {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
}

/* ------------------------------------------------------------
   Double-submit guard: disable the submit button while the
   request is in flight. Buttons that carry a name are skipped —
   disabling them would drop their value from the serialized
   form data (they are the payload for publish/hide and
   enable/disable actions).
   ------------------------------------------------------------ */
for (const form of document.querySelectorAll('form')) {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || form.dataset.submitting) {
            return;
        }
        const button = form.querySelector('button[type="submit"], input[type="submit"]');
        if (!button || button.disabled || button.name) {
            return;
        }
        form.dataset.submitting = '1';
        button.disabled = true;
        button.classList.add('is-busy');
    });
}

/* ------------------------------------------------------------
   Checkout: show the delivery location field only for delivery
   ------------------------------------------------------------ */
const checkout = document.querySelector('[data-checkout]');

if (checkout) {
    const locationField = checkout.querySelector('[data-delivery-location]');
    const locationInput = locationField.querySelector('input');
    const updateDeliveryField = () => {
        const isDelivery = checkout.querySelector('[name="fulfillment"]:checked')?.value === 'delivery';
        locationField.hidden = !isDelivery;
        locationInput.required = isDelivery;
    };

    checkout.addEventListener('change', updateDeliveryField);
    updateDeliveryField();
}

/* ------------------------------------------------------------
   Cart quantity steppers: − / + adjust the quantity and save it
   immediately. Typing a value and pressing Enter saves too.
   ------------------------------------------------------------ */
for (const stepper of document.querySelectorAll('.qty-stepper')) {
    const form = stepper.closest('form');
    const input = stepper.querySelector('input');
    const current = () => Math.min(99, Math.max(1, parseInt(input.value, 10) || 1));

    for (const button of stepper.querySelectorAll('.stepper-btn')) {
        button.addEventListener('click', () => {
            const next = Math.min(99, Math.max(1, current() + Number(button.dataset.step)));
            if (next === current() && input.value === String(next)) {
                return;
            }
            input.value = String(next);
            if (!form.dataset.submitting) {
                form.requestSubmit();
            }
        });
    }
    input.addEventListener('change', () => {
        if (input.value && !form.dataset.submitting) {
            input.value = String(current());
            form.requestSubmit();
        }
    });
}

/* ------------------------------------------------------------
   Password strength hint (registration and account forms).
   Advisory only — the server always validates for real.
   ------------------------------------------------------------ */
const strengthForm = document.querySelector('form[data-strength]');

if (strengthForm) {
    const password = strengthForm.querySelector('[name="password"]');
    const meter = strengthForm.querySelector('.strength');
    const label = meter.querySelector('.strength-label');
    const labels = ['', 'Could be stronger', 'Getting there', 'Good password', 'Strong password'];
    const score = (value) => {
        if (!value) {
            return 0;
        }
        let points = 0;
        if (value.length >= 8) points += 1;
        if (/\d/.test(value)) points += 1;
        if (/[\p{P}\p{S}]/u.test(value)) points += 1;
        if ((/[a-z]/.test(value) && /[A-Z]/.test(value)) || value.length >= 12) points += 1;
        return Math.max(1, points);
    };

    password.addEventListener('input', () => {
        const level = score(password.value);
        meter.classList.toggle('is-visible', level > 0);
        meter.dataset.level = String(level);
        label.textContent = labels[level];
    });
}

/* ------------------------------------------------------------
   Sticky header shadow
   ------------------------------------------------------------ */
const header = document.querySelector('[data-header]');

if (header) {
    const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 10);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
}
