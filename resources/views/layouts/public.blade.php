<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f4f0e8] text-zinc-900">
        {{ $slot }}
        @fluxScripts
    </body>
</html>
