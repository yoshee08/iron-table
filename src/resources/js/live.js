// SPDX-License-Identifier: AGPL-3.0-or-later
//
// The live part (#live) of the exercise screens. On each "changed" event of the
// exercise channel, the page loads itself again and replaces #live. Text that a
// user typed in a field with data-keep stays, and open <details> with an id stay open.
// The real clock counts each second.

const live = document.getElementById('live');

function valuesOf(root) {
    const values = { open: [...root.querySelectorAll('details[id][open]')].map((details) => details.id) };
    root.querySelectorAll('[data-keep]').forEach((field) => {
        if (field.type === 'checkbox') {
            values[field.id] = field.checked;
        } else if (field.multiple) {
            values[field.id] = [...field.selectedOptions].map((option) => option.value);
        } else {
            values[field.id] = field.value;
        }
    });
    return values;
}

function restore(values) {
    values.open.forEach((id) => document.getElementById(id)?.setAttribute('open', ''));
    delete values.open;
    for (const [id, value] of Object.entries(values)) {
        const field = document.getElementById(id);
        if (!field) continue;
        if (field.type === 'checkbox') {
            field.checked = value;
        } else if (field.multiple) {
            [...field.options].forEach((option) => { option.selected = value.includes(option.value); });
        } else {
            field.value = value;
        }
    }
}

function tick() {
    document.querySelectorAll('[data-since]').forEach((clock) => {
        const end = clock.dataset.until ? Number(clock.dataset.until) : Date.now();
        const seconds = Math.max(0, Math.floor((end - Number(clock.dataset.since)) / 1000));
        clock.textContent = [Math.floor(seconds / 3600), Math.floor(seconds / 60) % 60, seconds % 60]
            .map((part) => String(part).padStart(2, '0'))
            .join(':');
    });
}

async function refresh() {
    const response = await fetch(window.location.pathname + window.location.search, { credentials: 'same-origin' });
    if (!response.ok) return;
    const next = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById('live');
    if (!next) return;

    const values = valuesOf(live);
    const focused = document.activeElement?.id;
    live.innerHTML = next.innerHTML;
    restore(values);
    if (focused) document.getElementById(focused)?.focus();
    tick();
}

if (live) {
    window.Echo?.private(live.dataset.liveChannel).listen('.changed', refresh);
    // Safety net if the live connection drops.
    setInterval(refresh, 30000);
    setInterval(tick, 1000);
}
