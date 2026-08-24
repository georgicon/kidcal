<x-layouts::public :title="$calendar->name">
    <header class="border-b border-black/10 bg-[#3157d5] text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                <x-app-logo-icon class="size-6" />
                KidCal
            </a>
            @auth
                @can('update', $calendar)
                    <a href="{{ route('calendars.edit', $calendar) }}" class="rounded-full bg-white/10 px-4 py-2 text-sm font-medium hover:bg-white/20">Edit calendar</a>
                @endcan
            @else
                <a href="{{ route('login') }}" class="text-sm text-white/80 hover:text-white">Sign in</a>
            @endauth
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="mb-3 text-xs font-bold uppercase tracking-[0.24em] text-pink-600">Public calendar</p>
                <h1 class="font-serif text-4xl font-bold tracking-tight text-[#243b93] sm:text-5xl">{{ $calendar->name }}</h1>
                @if ($calendar->description)
                    <p class="mt-4 text-lg leading-8 text-zinc-600">{{ $calendar->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ preg_replace('/^https?:/', 'webcal:', route('calendars.ics', $calendar)) }}" class="shrink-0 rounded-full border border-[#3157d5]/20 bg-white px-5 py-3 text-sm font-semibold text-[#3157d5] hover:bg-[#eef4ff]">Subscribe in calendar app</a>
                @if ($hasEvents)
                    <a href="{{ route('calendars.pdf', $calendar) }}" class="shrink-0 rounded-full bg-[#3157d5] px-5 py-3 text-sm font-semibold text-white hover:bg-[#2646b5]">Download PDF</a>
                @endif
            </div>
        </div>

        <section class="rounded-2xl border border-black/10 bg-white shadow-[0_18px_60px_rgba(47,38,22,0.10)]">
            <div class="flex items-center justify-between gap-4 border-b border-zinc-200 px-4 py-4 sm:px-6">
                @if ($previousMonth)
                    <a href="{{ route('calendars.show', [$calendar, 'month' => $previousMonth->format('Y-m')]) }}" class="rounded-full border border-zinc-200 px-4 py-2 text-sm font-medium hover:bg-zinc-50">Previous</a>
                @else
                    <span class="w-20" aria-hidden="true"></span>
                @endif
                <h2 class="font-serif text-xl font-bold text-[#243b93] sm:text-2xl">{{ $month->format('F Y') }}</h2>
                @if ($nextMonth)
                    <a href="{{ route('calendars.show', [$calendar, 'month' => $nextMonth->format('Y-m')]) }}" class="rounded-full border border-zinc-200 px-4 py-2 text-sm font-medium hover:bg-zinc-50">Next</a>
                @else
                    <span class="w-20" aria-hidden="true"></span>
                @endif
            </div>

            <div class="grid grid-cols-7 border-b border-zinc-200 bg-[#eef4ff]">
                @foreach ($weekdays as $weekday)
                    <div class="px-1 py-3 text-center text-[10px] font-bold uppercase tracking-wider text-zinc-500 sm:text-xs">{{ $weekday }}</div>
                @endforeach
            </div>

            <div>
                @foreach ($days->chunk(7) as $week)
                    <div class="grid grid-cols-7 items-stretch">
                        @foreach ($week as $day)
                            <div data-date="{{ $day->format('Y-m-d') }}" class="min-w-0 min-h-20 border-b border-r border-zinc-100 p-2 sm:min-h-28 sm:p-3 {{ $day->month !== $month->month ? 'bg-zinc-50/70 text-zinc-400' : '' }}" style="height: auto; overflow: visible;">
                                <span class="inline-flex size-6 items-center justify-center rounded-full text-xs font-semibold {{ $day->isToday() ? 'bg-pink-500 text-white' : '' }}">{{ $day->day }}</span>
                                <div class="mt-1 space-y-1">
                                    @if ($day->month === $month->month)
                                        @foreach ($eventsByDay[$day->format('Y-m-d')] as $event)
                                            <div class="min-w-0 whitespace-normal rounded px-1.5 py-1 text-[10px] leading-tight text-white sm:text-xs" style="overflow-wrap: anywhere; background-color: {{ $event->display_color }}" title="{{ $event->title }}">
                                                <span class="block break-words text-[0.9em] font-medium opacity-90">
                                                    {{ $event->all_day ? 'All day' : $event->starts_at->setTimezone($calendar->timezone)->format('g:i a') }}@if ($event->location) - {{ $event->location }}@endif
                                                </span>
                                                <span class="mt-0.5 block break-words font-semibold">{{ $event->title }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>

        <p class="mt-4 text-right text-xs text-zinc-500">Times shown in {{ str_replace('_', ' ', $calendar->timezone) }}</p>
    </main>
</x-layouts::public>
