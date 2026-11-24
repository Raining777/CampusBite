'use strict';

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

for (const form of document.querySelectorAll('[data-confirm]')) {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
}
