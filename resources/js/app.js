const autosaveStates = new WeakMap();

function statusFor(form, message, type = 'idle') {
    const status = form.querySelector('[data-autosave-status]');

    if (!status) return;

    status.textContent = message;
    status.classList.toggle('text-red-600', type === 'error');
    status.classList.toggle('text-sky-600', type === 'saved');
    status.classList.toggle('text-zinc-500', type !== 'error' && type !== 'saved');
}

function stateFor(form) {
    if (!autosaveStates.has(form)) {
        autosaveStates.set(form, { timer: null, saving: false, queued: false });
    }

    return autosaveStates.get(form);
}

async function save(form) {
    const state = stateFor(form);

    if (state.saving) {
        state.queued = true;
        return;
    }

    state.saving = true;
    state.queued = false;
    statusFor(form, 'Saving...');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            let message = 'Could not save. Please check these fields.';

            if (response.status === 422) {
                const body = await response.json();
                message = Object.values(body.errors ?? {}).flat()[0] ?? message;
            }

            throw new Error(message);
        }

        statusFor(form, 'Saved', 'saved');

        if (form.matches('[data-calendar-settings]')) {
            const heading = document.querySelector('[data-calendar-heading]');
            const name = form.elements.namedItem('name');
            if (heading && name?.value) heading.textContent = name.value;
        }
    } catch (error) {
        statusFor(form, error.message || 'Could not save. Please try again.', 'error');
    } finally {
        state.saving = false;

        if (state.queued) save(form);
    }
}

function scheduleAutosave(form, delay) {
    const state = stateFor(form);
    clearTimeout(state.timer);
    statusFor(form, 'Unsaved changes');
    state.timer = setTimeout(() => save(form), delay);
}

document.addEventListener('input', (event) => {
    const form = event.target.closest('form[data-autosave]');
    if (form) scheduleAutosave(form, 650);
});

document.addEventListener('change', (event) => {
    const form = event.target.closest('form[data-autosave]');
    if (form) scheduleAutosave(form, 0);
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-autosave]');

    if (form) {
        event.preventDefault();
        scheduleAutosave(form, 0);
    }
});

function updateRecurrenceFields(form) {
    const type = form.querySelector('[data-recurrence-type]')?.value;

    form.querySelectorAll('[data-recurrence-options]').forEach((element) => {
        element.hidden = !type;
        element.querySelectorAll('input').forEach((input) => { input.disabled = !type; });
    });

    form.querySelectorAll('[data-weekday-options]').forEach((element) => {
        const weekly = type === 'weekly';
        element.hidden = !weekly;
        element.querySelectorAll('input').forEach((input) => { input.disabled = !weekly; });
    });
}

document.querySelectorAll('[data-recurrence-form]').forEach(updateRecurrenceFields);

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-recurrence-type]')) {
        updateRecurrenceFields(event.target.closest('[data-recurrence-form]'));
    }
});
