export function bindGallerySwipe(stage, navigate) {
    let start;
    stage.addEventListener('touchstart', (event) => {
        start = event.touches.length === 1 ? event.touches[0] : null;
    }, { passive: true });
    stage.addEventListener('touchcancel', () => { start = null; }, { passive: true });
    stage.addEventListener('touchend', (event) => {
        if (!start || !event.changedTouches.length) return;
        const touch = event.changedTouches[0];
        const horizontal = touch.clientX - start.clientX;
        const vertical = touch.clientY - start.clientY;
        start = null;
        if (Math.abs(horizontal) < 42 || Math.abs(horizontal) < Math.abs(vertical) * 1.4) return;

        if (event.cancelable) event.preventDefault();
        navigate(horizontal < 0 ? 1 : -1);
    }, { passive: false });
}
