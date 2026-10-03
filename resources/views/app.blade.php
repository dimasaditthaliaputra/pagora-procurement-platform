<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'PAGORA') }}</title>
        <meta name="description" content="PAGORA — Ekosistem Pengadaan Bahan Baku B2B Bebas Mark-Up, Transparan, dan Terkendali berbasis Harga Referensi BI dan 3-Way Matching.">
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/assets/logo/logo.webp') }}">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-cream-paper text-ink-black selection:bg-fresh-grass selection:text-ink-black">
        @inertia
    </body>
</html>