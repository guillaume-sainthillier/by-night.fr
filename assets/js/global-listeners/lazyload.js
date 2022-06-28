import 'lazysizes';

document.addEventListener('lazyloaded', (e) => {
    const image = e.target;
    if (!image.classList.contains('img-main')) {
        return;
    }

    image.closest('.image-wrapper').classList.add('main-image-loaded');
});

//no-op as we want to load lazysizes quickly
export default function lazyload() {}
