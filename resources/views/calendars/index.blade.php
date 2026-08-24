<x-layouts::app :title="__('My calendars')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="xl">My calendars</flux:heading>
                <flux:text class="mt-2">Build public calendars from all the schedules you follow.</flux:text>
            </div>

            <flux:button variant="primary" icon="plus" :href="route('calendars.create')" wire:navigate>
                New calendar
            </flux:button>
        </div>

        @if ($calendars->isEmpty())
            <div class="relative overflow-hidden rounded-3xl border border-dashed border-zinc-300 bg-zinc-50 px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
                <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-sky-400 via-pink-500 to-yellow-400"></div>
                <div class="mx-auto mb-5 flex size-14 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:ring-zinc-700">
                    <flux:icon.calendar-days class="size-7 text-pink-600" />
                </div>
                <flux:heading size="lg">Start with one shared calendar</flux:heading>
                <flux:text class="mx-auto mt-2 max-w-md">Give it a name and timezone. We’ll add iCal and webcal feeds next.</flux:text>
                <flux:button class="mt-6" variant="primary" :href="route('calendars.create')" wire:navigate>Create a calendar</flux:button>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($calendars as $calendar)
                    <article class="group rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-8 flex items-start justify-between gap-4">
                            <div class="flex size-11 items-center justify-center rounded-xl bg-pink-50 text-pink-700 dark:bg-pink-950 dark:text-pink-300">
                                <flux:icon.calendar-days class="size-5" />
                            </div>
                            <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-950 dark:text-sky-300">Public</span>
                        </div>
                        <flux:heading size="lg">{{ $calendar->name }}</flux:heading>
                        <flux:text class="mt-1 line-clamp-2 min-h-10">{{ $calendar->description ?: 'No description yet.' }}</flux:text>
                        <div class="mt-6 flex items-center justify-between border-t border-zinc-100 pt-4 dark:border-zinc-800">
                            <span class="text-xs text-zinc-500">{{ $calendar->timezone }}</span>
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="ghost" :href="route('calendars.show', $calendar)" target="_blank">View</flux:button>
                                <flux:button size="sm" :href="route('calendars.edit', $calendar)" wire:navigate>Edit</flux:button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
