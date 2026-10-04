@props(['titre', 'description' => null])
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $titre }} — LY AGRICOLE</title>
        @if ($description)<meta name="description" content="{{ $description }}">@endif
        <meta name="theme-color" content="#123524">
        <link rel="icon" type="image/png" href="{{ asset('images/logo-yl-agro.png') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script>document.documentElement.classList.add('js');</script>
        @include('vitrine.style')
    </head>
    <body class="v min-h-screen antialiased">
        <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2">Aller au contenu</a>

        @include('vitrine.entete', ['ancre' => route('accueil')])

        <main id="contenu">
            {{ $slot }}
        </main>

        @include('vitrine.pied')

        @include('vitrine.scripts')
    </body>
</html>
