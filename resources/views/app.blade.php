<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'light') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "light" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @php($seo = $page['props']['seo'] ?? null)
        @if (is_array($seo))
            {{-- BR-ND-78 / AC-06-30: thẻ chia sẻ có sẵn trong mã nguồn trang công khai --}}
            <meta name="description" content="{{ $seo['description'] ?? '' }}">
            <meta property="og:title" content="{{ $seo['title'] ?? '' }}">
            <meta property="og:description" content="{{ $seo['description'] ?? '' }}">
            <meta property="og:url" content="{{ $seo['url'] ?? '' }}">
            @if (! empty($seo['image']))
                <meta property="og:image" content="{{ $seo['image'] }}">
            @endif
            <link rel="canonical" href="{{ $seo['url'] ?? '' }}">
        @endif
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        {{-- P8 M28: PWA --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#0f172a">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Bricolage+Grotesque:opsz,wght@12..96,500..800&display=swap">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ is_array($seo) && ! empty($seo['title']) ? $seo['title'].' - '.config('app.name') : config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
