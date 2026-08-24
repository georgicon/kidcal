<x-layouts::app :title="__('Edit :name', ['name' => $calendar->name])">
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-8">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:text class="mb-2 text-xs font-semibold uppercase tracking-[0.2em] text-orange-600">Calendar settings</flux:text>
                <flux:heading size="xl">{{ $calendar->name }}</flux:heading>
                <flux:text class="mt-2">Your public URL stays stable even when the calendar name changes.</flux:text>
            </div>
            <flux:button icon="arrow-up-right" :href="route('calendars.show', $calendar)" target="_blank">View public calendar</flux:button>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <form method="POST" action="{{ route('calendars.update', $calendar) }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-700 dark:bg-zinc-900">
                @csrf
                @method('PUT')
                @include('calendars.partials.form')

                <div class="mt-8 flex justify-end border-t border-zinc-100 pt-6 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary">Save settings</flux:button>
                </div>
            </form>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading>Public link</flux:heading>
                    <flux:text class="mt-2 break-all text-sm">{{ route('calendars.show', $calendar) }}</flux:text>
                    <flux:button class="mt-4 w-full" size="sm" :href="route('calendars.show', $calendar)" target="_blank">Open calendar</flux:button>
                </div>
                <div class="rounded-2xl border border-dashed border-zinc-300 p-5 dark:border-zinc-700">
                    <flux:heading>Import window</flux:heading>
                    <flux:text class="mt-2 text-sm">Feeds are expanded from one year ago through two years ahead and refreshed hourly.</flux:text>
                </div>
            </aside>
        </div>

        <section class="space-y-5">
            <div>
                <flux:heading size="lg">Ad-hoc events</flux:heading>
                <flux:text class="mt-1">Add events that do not belong to any imported feed.</flux:text>
            </div>

            <form method="POST" action="{{ route('manual-events.store', $calendar) }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                @csrf
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.3fr_1fr_auto_auto_auto_auto] xl:items-end">
                    <flux:input name="title" label="Event name" :value="old('title')" required maxlength="200" />
                    <flux:input name="location" label="Location" :value="old('location')" maxlength="200" />
                    <flux:input type="date" name="date" label="Date" :value="old('date', now($calendar->timezone)->format('Y-m-d'))" required />
                    <flux:input type="time" name="start_time" label="Starts" :value="old('start_time', '09:00')" />
                    <flux:input type="time" name="end_time" label="Ends" :value="old('end_time', '10:00')" />
                    <div>
                        <label for="event-color" class="mb-2 block text-sm font-medium">Color</label>
                        <input id="event-color" type="color" name="color" value="{{ old('color', '#173f35') }}" class="h-10 w-16 cursor-pointer rounded-lg border border-zinc-300 bg-white p-1 dark:border-zinc-600 dark:bg-zinc-800">
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between gap-4">
                    <div>
                        <input type="hidden" name="all_day" value="0">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="all_day" value="1" @checked(old('all_day')) class="rounded border-zinc-300">
                            All-day event
                        </label>
                    </div>
                    <flux:button type="submit" variant="primary" icon="plus">Add event</flux:button>
                </div>
            </form>

            @if ($calendar->manualEvents->isNotEmpty())
                <div class="space-y-3">
                    @foreach ($calendar->manualEvents as $event)
                        @php
                            $localStart = $event->starts_at->setTimezone($calendar->timezone);
                            $localEnd = $event->ends_at->setTimezone($calendar->timezone);
                        @endphp
                        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <form method="POST" action="{{ route('manual-events.update', [$calendar, $event]) }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.3fr_1fr_auto_auto_auto_auto_auto] xl:items-end">
                                @csrf
                                @method('PUT')
                                <flux:input name="title" label="Event name" :value="$event->title" required maxlength="200" />
                                <flux:input name="location" label="Location" :value="$event->location" maxlength="200" />
                                <flux:input type="date" name="date" label="Date" :value="$localStart->format('Y-m-d')" required />
                                <flux:input type="time" name="start_time" label="Starts" :value="$localStart->format('H:i')" />
                                <flux:input type="time" name="end_time" label="Ends" :value="$localEnd->format('H:i')" />
                                <div>
                                    <label for="event-color-{{ $event->id }}" class="mb-2 block text-sm font-medium">Color</label>
                                    <input id="event-color-{{ $event->id }}" type="color" name="color" value="{{ $event->color }}" class="h-10 w-16 cursor-pointer rounded-lg border border-zinc-300 bg-white p-1 dark:border-zinc-600 dark:bg-zinc-800">
                                </div>
                                <div class="pb-2">
                                    <input type="hidden" name="all_day" value="0">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="all_day" value="1" @checked($event->all_day) class="rounded border-zinc-300">
                                        All day
                                    </label>
                                </div>
                                <div class="md:col-span-2 xl:col-span-7 flex justify-end border-t border-zinc-100 pt-3 dark:border-zinc-800">
                                    <flux:button type="submit">Save event</flux:button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('manual-events.destroy', [$calendar, $event]) }}" class="mt-3 flex justify-end" onsubmit="return confirm('Remove this event?')">
                                @csrf
                                @method('DELETE')
                                <flux:button type="submit" size="sm" variant="danger">Remove</flux:button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-5">
            <div>
                <flux:heading size="lg">Calendar feeds</flux:heading>
                <flux:text class="mt-1">Combine iCal and webcal subscriptions into this calendar.</flux:text>
            </div>

            <form method="POST" action="{{ route('calendar-sources.store', $calendar) }}" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                @csrf
                <flux:textarea
                    name="links"
                    label="Paste feed links"
                    description="Add up to 50 iCal or webcal links. They can be on separate lines or mixed into copied text."
                    :value="old('links')"
                    placeholder="webcal://example.org/events.ics&#10;https://another-site.org/calendar.ics"
                    rows="6"
                    required
                />
                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <label for="source-color" class="mb-2 block text-sm font-medium">Starting color</label>
                        <input id="source-color" type="color" name="color" value="{{ old('color', '#2563eb') }}" class="h-10 w-16 cursor-pointer rounded-lg border border-zinc-300 bg-white p-1 dark:border-zinc-600 dark:bg-zinc-800">
                        <p class="mt-1 text-xs text-zinc-500">You can change each feed separately afterward.</p>
                    </div>
                    <flux:button type="submit" variant="primary" icon="plus">Add feeds</flux:button>
                </div>
            </form>

            @if ($calendar->sources->isEmpty())
                <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center dark:border-zinc-700">
                    <flux:text>No feeds connected yet. Add your first subscription above.</flux:text>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($calendar->sources as $source)
                        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                            <form method="POST" action="{{ route('calendar-sources.update', [$calendar, $source]) }}" class="grid gap-4 lg:grid-cols-[1fr_auto_auto_auto] lg:items-end">
                                @csrf
                                @method('PUT')
                                <div>
                                    <flux:input name="name" label="Feed name" :value="$source->name" required maxlength="100" />
                                    <p class="mt-1 max-w-xl truncate text-xs text-zinc-500" title="{{ $source->url }}">{{ $source->url }}</p>
                                </div>
                                <div>
                                    <label for="source-color-{{ $source->id }}" class="mb-2 block text-sm font-medium">Color</label>
                                    <input id="source-color-{{ $source->id }}" type="color" name="color" value="{{ $source->color }}" class="h-10 w-16 cursor-pointer rounded-lg border border-zinc-300 bg-white p-1 dark:border-zinc-600 dark:bg-zinc-800">
                                </div>
                                <div class="pb-2">
                                    <input type="hidden" name="enabled" value="0">
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="enabled" value="1" @checked($source->enabled) class="rounded border-zinc-300">
                                        Visible
                                    </label>
                                </div>
                                <flux:button type="submit">Save</flux:button>
                            </form>

                            <div class="mt-4 flex flex-col gap-3 border-t border-zinc-100 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                                <div class="text-sm">
                                    <span class="font-medium {{ $source->sync_status === 'failed' ? 'text-red-600' : ($source->sync_status === 'synced' ? 'text-emerald-600' : 'text-amber-600') }}">
                                        {{ ucfirst($source->sync_status) }}
                                    </span>
                                    <span class="ml-2 text-zinc-500">{{ $source->events_count }} events</span>
                                    @if ($source->last_synced_at)
                                        <span class="ml-2 text-zinc-500">Updated {{ $source->last_synced_at->diffForHumans() }}</span>
                                    @endif
                                    @if ($source->sync_error)
                                        <p class="mt-1 text-xs text-red-600">{{ $source->sync_error }}</p>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('calendar-sources.refresh', [$calendar, $source]) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm" variant="ghost" icon="arrow-path">Refresh</flux:button>
                                    </form>
                                    <form method="POST" action="{{ route('calendar-sources.destroy', [$calendar, $source]) }}" onsubmit="return confirm('Remove this feed and all of its imported events?')">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" size="sm" variant="danger">Remove</flux:button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts::app>
