<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'Kids sports, one calendar'])
    </head>
    <body class="min-h-screen bg-[#f8f7ff] text-[#18245c] antialiased">
        <div class="relative isolate overflow-hidden">
            <div class="absolute inset-x-0 top-0 -z-10 h-[38rem] bg-[radial-gradient(circle_at_78%_8%,rgba(236,72,153,0.20),transparent_30%),radial-gradient(circle_at_20%_30%,rgba(56,189,248,0.18),transparent_28%),linear-gradient(145deg,#fffbea_10%,#f1efff_100%)]"></div>
            <div class="absolute -left-20 top-56 -z-10 size-64 rounded-full border-[44px] border-[#38bdf8]/20"></div>

            <header class="mx-auto flex max-w-7xl items-center justify-between px-5 py-6 sm:px-8">
                <a href="{{ route('home') }}" class="flex items-center gap-3 font-bold tracking-tight">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-[#3157d5] text-white shadow-sm">
                        <x-app-logo-icon class="size-7" />
                    </span>
                    <span class="text-xl">KidCal</span>
                </a>

                <nav class="flex items-center gap-3 text-sm font-semibold">
                    @auth
                        <a href="{{ route('calendars.index') }}" class="rounded-full bg-[#3157d5] px-5 py-2.5 text-white transition hover:bg-[#2646b5]">My calendars</a>
                    @else
                        <a href="{{ route('login') }}" class="px-3 py-2 text-[#18245c]/70 transition hover:text-[#18245c]">Sign in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-full bg-[#3157d5] px-5 py-2.5 text-white transition hover:bg-[#2646b5]">Get started</a>
                        @endif
                    @endauth
                </nav>
            </header>

            <main>
                <section class="mx-auto grid max-w-7xl items-center gap-12 px-5 pb-24 pt-14 sm:px-8 lg:grid-cols-[1.05fr_0.95fr] lg:pt-20">
                    <div class="max-w-2xl">
                        <p class="mb-5 inline-flex items-center gap-2 rounded-full border border-[#3157d5]/10 bg-white/75 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-[#3157d5] shadow-sm">
                            Every team. One family calendar.
                        </p>
                        <h1 class="font-serif text-5xl font-bold leading-[0.98] tracking-tight sm:text-6xl lg:text-7xl">
                            Less schedule juggling. <span class="text-[#e83e8c]">More time cheering.</span>
                        </h1>
                        <p class="mt-7 max-w-xl text-lg leading-8 text-[#4c5680] sm:text-xl">
                            KidCal gathers all your kids’ team calendars into one simple view, color-codes every schedule, and prints a calendar the whole family can follow.
                        </p>
                        <div class="mt-9 flex flex-wrap items-center gap-4">
                            @auth
                                <a href="{{ route('calendars.create') }}" class="rounded-full bg-[#3157d5] px-7 py-3.5 font-bold text-white shadow-lg shadow-[#3157d5]/20 transition hover:-translate-y-0.5 hover:bg-[#2646b5]">Create a calendar</a>
                            @else
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="rounded-full bg-[#3157d5] px-7 py-3.5 font-bold text-white shadow-lg shadow-[#3157d5]/20 transition hover:-translate-y-0.5 hover:bg-[#2646b5]">Build your KidCal</a>
                                @endif
                            @endauth
                            <span class="text-sm font-medium text-[#68719a]">Private, practical, and made for busy families.</span>
                        </div>
                    </div>

                    <div class="relative mx-auto w-full max-w-xl">
                        <div class="absolute -inset-5 -z-10 rotate-2 rounded-[2.5rem] bg-[#facc15]/25"></div>
                        <div class="overflow-hidden rounded-[2rem] border border-[#3157d5]/10 bg-white p-4 shadow-[0_30px_80px_rgba(49,87,213,0.16)] sm:p-6">
                            <div class="mb-5 flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#e83e8c]">The Miller family</p>
                                    <h2 class="mt-1 font-serif text-2xl font-bold">September</h2>
                                </div>
                                <span class="rounded-full bg-[#e8f2ff] px-3 py-1.5 text-xs font-bold">3 teams</span>
                            </div>
                            <div class="grid grid-cols-7 border-l border-t border-[#d8dfda] text-center text-[10px] font-bold uppercase text-[#73847e]">
                                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)
                                    <div class="border-b border-r border-[#d8dfda] py-2">{{ $day }}</div>
                                @endforeach
                                @foreach (range(1, 21) as $date)
                                    <div class="min-h-16 border-b border-r border-[#d8dfda] p-1 text-left text-[9px] sm:min-h-20">
                                        <span class="font-bold text-[#52665f]">{{ $date }}</span>
                                        @if ($date === 3)
                                            <div class="mt-1 rounded bg-[#e83e8c] p-1 leading-tight text-white">6:00 - Soccer</div>
                                        @elseif ($date === 8)
                                            <div class="mt-1 rounded bg-[#3157d5] p-1 leading-tight text-white">7:15 - Hockey</div>
                                        @elseif ($date === 12)
                                            <div class="mt-1 rounded bg-[#ca8a04] p-1 leading-tight text-white">9:30 - Football</div>
                                        @elseif ($date === 17)
                                            <div class="mt-1 rounded bg-[#38a4d8] p-1 leading-tight text-white">5:45 - Practice</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                <section class="bg-[#243b93] px-5 py-20 text-white sm:px-8">
                    <div class="mx-auto max-w-7xl">
                        <div class="mb-12 max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-[#f9a8d4]">Built for the sideline</p>
                            <h2 class="mt-3 font-serif text-4xl font-bold tracking-tight sm:text-5xl">A calmer way to run the week.</h2>
                        </div>
                        <div class="grid gap-5 md:grid-cols-3">
                            <article class="rounded-2xl border border-white/10 bg-white/[0.06] p-6">
                                <span class="text-sm font-bold text-[#facc15]">01</span>
                                <h3 class="mt-5 text-xl font-bold">Paste every schedule</h3>
                                <p class="mt-3 leading-7 text-white/65">Add iCal and webcal links in one batch. KidCal keeps them refreshed for you.</p>
                            </article>
                            <article class="rounded-2xl border border-white/10 bg-white/[0.06] p-6">
                                <span class="text-sm font-bold text-[#7dd3fc]">02</span>
                                <h3 class="mt-5 text-xl font-bold">Know who plays where</h3>
                                <p class="mt-3 leading-7 text-white/65">Use team colors, add family-only events, and see times and locations at a glance.</p>
                            </article>
                            <article class="rounded-2xl border border-white/10 bg-white/[0.06] p-6">
                                <span class="text-sm font-bold text-[#f9a8d4]">03</span>
                                <h3 class="mt-5 text-xl font-bold">Put it on the fridge</h3>
                                <p class="mt-3 leading-7 text-white/65">Download a clean landscape PDF with one useful month on every page.</p>
                            </article>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="bg-[#18245c] px-5 py-7 text-sm text-white/55 sm:px-8">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4">
                    <span class="font-bold text-white">KidCal</span>
                    <span>Kids’ sports, finally in one place.</span>
                </div>
            </footer>
        </div>
        @fluxScripts
    </body>
</html>
