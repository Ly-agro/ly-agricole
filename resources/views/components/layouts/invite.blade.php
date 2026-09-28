<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? 'Connexion' }} — {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
        <main class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <div class="mb-8 text-center">
                <p class="text-2xl font-semibold tracking-wide text-emerald-800">LY AGRICOLE</p>
                <p class="mt-1 text-sm text-stone-500">Cultiver – Élever – Durer</p>
            </div>

            <div class="w-full max-w-sm rounded-xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
