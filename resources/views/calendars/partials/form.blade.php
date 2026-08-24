<div class="space-y-6">
    <flux:input
        name="name"
        label="Calendar name"
        :value="old('name', $calendar?->name)"
        placeholder="Community events"
        maxlength="100"
        required
        autofocus
    />

    <flux:textarea
        name="description"
        label="Description"
        :value="old('description', $calendar?->description)"
        placeholder="A short introduction visitors will see above the calendar."
        rows="3"
        maxlength="1000"
    />

    <div class="grid gap-6 sm:grid-cols-2">
        <flux:select name="timezone" label="Timezone" required>
            @foreach ($timezones as $timezone)
                <option value="{{ $timezone }}" @selected(old('timezone', $calendar?->timezone ?? 'UTC') === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
            @endforeach
        </flux:select>

        <flux:select name="week_starts_on" label="Week starts on" required>
            <option value="0" @selected((int) old('week_starts_on', $calendar?->week_starts_on ?? 0) === 0)>Sunday</option>
            <option value="1" @selected((int) old('week_starts_on', $calendar?->week_starts_on ?? 0) === 1)>Monday</option>
        </flux:select>
    </div>
</div>
