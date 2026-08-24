<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#f7f7ff] text-zinc-900">
        {{ $slot }}
        @fluxScripts
    </body>
</html>
