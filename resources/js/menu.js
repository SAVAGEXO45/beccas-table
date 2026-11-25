import { bindGallerySwipe } from './menu-gestures.js';

function wrap(index, count) {
    return (index + count) % count;
}

function arrows(event, navigate) {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
    event.preventDefault();
    navigate(event.key === 'ArrowLeft' ? -1 : 1);
}

const galleries = document.querySelectorAll('[data-gallery]');
if (galleries.length) {
    const dialog = document.createElement('dialog');
    dialog.className = 'menu-lightbox';
    dialog.setAttribute('aria-label', 'Food photograph gallery');
    dialog.innerHTML = `
        <div class="menu-lightbox-content">
            <button class="menu-lightbox-close" type="button" aria-label="Close gallery" autofocus>×</button>
            <img alt="" decoding="async">
            <button class="menu-gallery-arrow previous" type="button" aria-label="Previous large photograph">‹</button>
            <button class="menu-gallery-arrow next" type="button" aria-label="Next large photograph">›</button>
            <p aria-live="polite"></p>
        </div>`;
    document.body.append(dialog);

    const largeImage = dialog.querySelector('img');
    const counter = dialog.querySelector('p');
    const previous = dialog.querySelector('.previous');
    const next = dialog.querySelector('.next');
    let active;
    let openButton;
    let index;
    const show = (position) => {
        index = wrap(position, active.length);
        const photo = active[index];
        largeImage.src = photo.full;
        largeImage.alt = photo.description;
        counter.textContent = (index + 1) + ' of ' + active.length;
    };
    const step = (direction) => { if (active.length > 1) show(index + direction); };
    previous.addEventListener('click', () => step(-1));
    next.addEventListener('click', () => step(1));
    dialog.querySelector('.menu-lightbox-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('keydown', (event) => arrows(event, step));
    dialog.addEventListener('close', () => { openButton?.focus(); });
    bindGallerySwipe(largeImage, step);

    for (const gallery of galleries) {
        const photos = [...gallery.querySelector('[data-gallery-photos]').content.children].map((item) => item.dataset);
        const image = gallery.querySelector('.menu-gallery-open img');
        const thumbnails = [...gallery.querySelectorAll('[data-gallery-thumb]')];
        const trigger = gallery.querySelector('[data-gallery-open]');
        const status = gallery.querySelector('[data-gallery-status]');
        let current = 0;
        const select = (position) => {
            current = wrap(position, photos.length);
            const photo = photos[current];
            image.srcset = photo.srcset;
            image.src = photo.src;
            image.alt = photo.description;
            thumbnails.forEach((button, i) => button.setAttribute('aria-pressed', String(i === current)));
            status.textContent = `Photograph ${current + 1} of ${photos.length}`;
            if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
                image.animate([{ opacity: 0.72 }, { opacity: 1 }], { duration: 170 });
            }
        };
        const navigate = (direction) => { if (photos.length > 1) select(current + direction); };
        gallery.querySelectorAll('[data-gallery-step]').forEach((button) => {
            button.addEventListener('click', () => navigate(Number(button.dataset.galleryStep)));
        });
        thumbnails.forEach((button, i) => button.addEventListener('click', () => select(i)));
        gallery.addEventListener('keydown', (event) => arrows(event, navigate));
        bindGallerySwipe(gallery.querySelector('[data-gallery-stage]'), navigate);
        trigger.addEventListener('click', () => {
            active = photos;
            openButton = trigger;
            previous.hidden = next.hidden = photos.length < 2;
            show(current);
            dialog.showModal();
        });
    }
}

const currency = new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' });
document.querySelectorAll('[data-meal-order]').forEach((form) => {
    const output = form.querySelector('[data-meal-total]');
    const price = () => {
        const option = form.elements.variation?.selectedOptions[0];
        const extras = [...form.querySelectorAll('input[data-price]:checked')];
        const unit = Number(option?.dataset.price ?? form.dataset.basePrice)
            + extras.reduce((sum, extra) => sum + Number(extra.dataset.price), 0);
        const quantity = Number(form.elements.quantity.value);
        output.textContent = ` · ${currency.format(unit * quantity / 100)}`;
        output.hidden = quantity < 1 || quantity > 30;
    };
    form.addEventListener('input', price);
    price();
});

document.querySelectorAll('[data-option-section]').forEach((section) => {
    const rows = section.querySelector('[data-option-rows]');
    const template = section.querySelector('template');
    let index = rows.children.length;
    section.querySelector('[data-add-option]').addEventListener('click', () => {
        if (rows.children.length >= 20) return;
        const row = template.content.cloneNode(true);
        row.querySelectorAll('[name]').forEach((field) => { field.name = field.name.replace('__index__', index); });
        rows.append(row);
        index += 1;
        rows.lastElementChild.querySelector('input').focus();
    });
    rows.addEventListener('click', (event) => {
        if (event.target.matches('[data-remove-option]')) event.target.closest('[data-option-row]').remove();
    });
});

document.querySelectorAll('[data-menu-upload]').forEach((input) => {
    const previews = input.form.querySelector('[data-menu-previews]');
    let urls = [];
    input.addEventListener('change', () => {
        urls.forEach((url) => URL.revokeObjectURL(url));
        urls = Array.from(input.files, (file) => URL.createObjectURL(file));
        previews.replaceChildren(...urls.map((url, index) => {
            const image = new Image();
            image.src = url;
            image.alt = `Upload preview ${index + 1}`;
            return image;
        }));
    });
});




