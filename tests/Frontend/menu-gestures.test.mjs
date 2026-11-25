import { strict as assert } from 'node:assert';
import { test } from 'node:test';
import { bindGallerySwipe } from '../../resources/js/menu-gestures.js';

class TouchFixture extends Event {
    constructor(name, data = {}) {
        super(name, { cancelable: true });
        Object.assign(this, data);
    }
}

const point = (clientX, clientY) => ({ clientX, clientY });

const move = (stage, from, to) => {
    stage.dispatchEvent(new TouchFixture('touchstart', { touches: [from] }));
    const end = new TouchFixture('touchend', { changedTouches: [to] });
    stage.dispatchEvent(end);
    return end.defaultPrevented;
};

test('horizontal swipes move only their own gallery in the correct direction', () => {
    const first = new EventTarget();
    const second = new EventTarget();
    const calls = [];
    bindGallerySwipe(first, (direction) => calls.push(['first', direction]));
    bindGallerySwipe(second, (direction) => calls.push(['second', direction]));

    assert.equal(move(first, point(160, 80), point(70, 84)), true);
    assert.equal(move(second, point(70, 80), point(140, 84)), true);
    assert.deepEqual(calls, [['first', 1], ['second', -1]]);
});

test('vertical, short, cancelled and multi-touch gestures preserve native scrolling', () => {
    const stage = new EventTarget();
    bindGallerySwipe(stage, () => assert.fail('The gallery should not move'));

    assert.equal(move(stage, point(150, 80), point(95, 210)), false);
    assert.equal(move(stage, point(150, 80), point(128, 82)), false);
    stage.dispatchEvent(new TouchFixture('touchstart', { touches: [point(150, 80)] }));
    stage.dispatchEvent(new TouchFixture('touchcancel'));
    stage.dispatchEvent(new TouchFixture('touchend', { changedTouches: [point(30, 82)] }));
    stage.dispatchEvent(new TouchFixture('touchstart', { touches: [point(150, 80), point(120, 80)] }));
    stage.dispatchEvent(new TouchFixture('touchend', { changedTouches: [point(30, 82)] }));
});
