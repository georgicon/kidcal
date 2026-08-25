@php
    $recurrenceType = old('recurrence_type', $event?->recurrence_type);
    $recurrenceWeekdays = old('recurrence_weekdays', $event?->recurrence_weekdays ?? []);
@endphp

<fieldset class="mt-5 border-t border-zinc-100 pt-5 dark:border-zinc-800">
    <legend class="px-1 text-sm font-semibold">Repeat</legend>
    <div class="grid gap-4 md:grid-cols-3">
        <div>
            <label for="recurrence-type-{{ $fieldId }}" class="mb-2 block text-sm font-medium">Frequency</label>
            <select id="recurrence-type-{{ $fieldId }}" name="recurrence_type" data-recurrence-type class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                <option value="" @selected(! $recurrenceType)>Does not repeat</option>
                <option value="daily" @selected($recurrenceType === 'daily')>Daily</option>
                <option value="weekly" @selected($recurrenceType === 'weekly')>Weekly</option>
                <option value="monthly" @selected($recurrenceType === 'monthly')>Monthly</option>
                <option value="yearly" @selected($recurrenceType === 'yearly')>Yearly</option>
            </select>
        </div>
        <div data-recurrence-options>
            <flux:input type="number" name="recurrence_interval" label="Repeat every" :value="old('recurrence_interval', $event?->recurrence_interval ?? 1)" min="1" max="52" required />
        </div>
        <div data-recurrence-options>
            <flux:input type="date" name="recurrence_until" label="Repeat until" :value="old('recurrence_until', $event?->recurrence_until?->format('Y-m-d'))" required />
        </div>
    </div>
    <div class="mt-4" data-weekday-options>
        <span class="block text-sm font-medium">On these days</span>
        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-2">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday => $label)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="recurrence_weekdays[]" value="{{ $weekday }}" @checked(in_array($weekday, $recurrenceWeekdays)) class="rounded border-zinc-300">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>
</fieldset>
