<x-layouts::app :title="__('Create calendar')">
    <div class="mx-auto w-full max-w-3xl">
        <div class="mb-8">
            <flux:button variant="ghost" icon="arrow-left" :href="route('calendars.index')" wire:navigate>My calendars</flux:button>
            <flux:heading size="xl" class="mt-5">Create a public calendar</flux:heading>
            <flux:text class="mt-2">Set the basics now. You’ll connect feeds on the next screen.</flux:text>
        </div>

        <form method="POST" action="{{ route('calendars.store') }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm sm:p-8 dark:border-zinc-700 dark:bg-zinc-900">
            @csrf
            @include('calendars.partials.form', ['calendar' => null])

            <div class="mt-8 flex justify-end gap-3 border-t border-zinc-100 pt-6 dark:border-zinc-800">
                <flux:button :href="route('calendars.index')" wire:navigate>Cancel</flux:button>
                <flux:button type="submit" variant="primary">Create calendar</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
