import './menu.js';

document.documentElement.classList.add('js');

const navToggle = document.querySelector('[data-nav-toggle]');
if (navToggle) {
    const navigation = document.getElementById(navToggle.getAttribute('aria-controls'));

    navToggle.addEventListener('click', () => {
        const isOpen = navigation.classList.toggle('is-open');
        navToggle.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && navigation.classList.contains('is-open')) {
            navigation.classList.remove('is-open');
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.focus();
        }
    });
}

const checkout = document.querySelector('[data-checkout]');
const summary = document.querySelector('[data-summary]');
if (checkout && summary) {
    const format = new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' });
    const delivery = checkout.querySelector('[data-delivery]');
    const pickup = checkout.querySelector('[data-pickup]');
    const syncSummary = () => {
        const isDelivery = checkout.querySelector('input[name="fulfilment"]:checked')?.value === 'delivery';
        const area = checkout.querySelector('select[name="delivery_area"]');
        const address = checkout.querySelector('textarea[name="address"]');
        const fee = isDelivery ? Number(area?.selectedOptions[0]?.dataset.fee || 0) : 0;
        if (delivery) delivery.hidden = !isDelivery;
        pickup.hidden = isDelivery;
        if (area) area.required = isDelivery;
        if (address) address.required = isDelivery;
        summary.querySelector('[data-fee-display]').textContent = format.format(fee / 100);
        summary.querySelector('[data-total-display]').textContent = format.format((Number(summary.dataset.subtotal) + fee) / 100);
    };
    const bankInstructions = checkout.querySelector('[data-bank-instructions]');
    const submit = checkout.querySelector('[data-place-order]');
    const status = checkout.querySelector('[data-checkout-status]');
    const paymentMethod = () => checkout.querySelector('[name="payment_method"]:checked')?.value;
    const syncPayment = () => {
        if (bankInstructions) bankInstructions.hidden = paymentMethod() !== 'bank_transfer';
        if (submit) submit.textContent = paymentMethod() === 'paystack' ? 'Continue to Paystack' : 'Place order';
    };

    checkout.addEventListener('change', () => {
        syncSummary();
        syncPayment();
    });
    checkout.addEventListener('submit', () => {
        if (!submit) return;
        submit.disabled = true;
        status.hidden = false;
        status.textContent = paymentMethod() === 'paystack' ? 'Opening secure payment…' : 'Creating your order…';
    });
    window.addEventListener('pageshow', () => {
        if (submit) {
            submit.disabled = false;
            status.hidden = true;
        }
    });
    syncSummary();
    syncPayment();
}

document.querySelectorAll('[data-copy-account]').forEach((button) => {
    if (!navigator.clipboard?.writeText) return;
    button.hidden = false;
    button.addEventListener('click', async () => {
        const status = button.parentElement.querySelector('[data-copy-status]');
        try {
            await navigator.clipboard.writeText(button.dataset.copyAccount);
            status.textContent = 'Account number copied.';
        } catch {
            status.textContent = 'Copy the account number shown above.';
        }
    });
});

document.querySelectorAll('[data-cart-quantity]').forEach((form) => {
    form.querySelector('input[type="number"]').addEventListener('change', () => {
        if (form.reportValidity()) form.requestSubmit();
    });
});

document.querySelectorAll('[data-print]').forEach((button) => {
    button.addEventListener('click', () => window.print());
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('form[data-confirm]') && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

document.querySelectorAll('details[data-catalog-category]').forEach((group) => {
    const content = group.querySelector('[data-catalog-content]');
    const rows = content.querySelector('[data-catalog-rows]');
    const status = content.querySelector('[data-catalog-status]');
    let loaded = false;

    const loadPage = async (page = '1') => {
        const url = new URL(group.dataset.catalogCategory);
        url.searchParams.set('page', page);
        content.setAttribute('aria-busy', 'true');
        status.hidden = false;
        status.textContent = 'Loading meals…';

        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok || response.redirected) throw new Error('Unable to load meals');
            rows.innerHTML = await response.text();
            loaded = true;
            status.hidden = true;
            return true;
        } catch {
            status.textContent = 'Could not load meals here. Use the category link to continue.';
            return false;
        } finally {
            content.removeAttribute('aria-busy');
        }
    };

    group.addEventListener('toggle', () => {
        if (group.open && !loaded && !content.hasAttribute('aria-busy')) loadPage();
    });
    content.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-catalog-pagination] a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        if (content.hasAttribute('aria-busy')) return;
        const page = new URL(link.href).searchParams.get('page') ?? '1';
        if (await loadPage(page)) {
            group.querySelector('summary').focus();
        } else {
            window.location.assign(link.href);
        }
    });
});

const photoInput = document.querySelector('[data-photo-input]');
const preview = document.querySelector('[data-photo-preview]');
if (photoInput && preview) {
    let previewUrl;
    photoInput.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        preview.hidden = !photoInput.files.length;
        if (!preview.hidden) {
            previewUrl = URL.createObjectURL(photoInput.files[0]);
            preview.src = previewUrl;
        }
    });
}

