<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ config('app.name', 'Vyapaargo') }} &mdash; SME Business Platform</title>
<link rel="icon" type="image/png" href="{{ asset('images/logo-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/logo-180.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Vyapaargo">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<!-- Public Layout CSS -->
@include('layouts.partials.public_css')
@stack('styles')
</head>
<body>

<!-- Public Header Navbar -->
@include('layouts.partials.public_header')

<!-- Main Page Content -->
<main>
    @yield('content')
</main>

<!-- Public Footer -->
@include('layouts.partials.public_footer')

<!-- Public JS -->
@include('layouts.partials.public_js')
@stack('scripts')

<x-required-asterisks />
@include('layouts.partials.add-to-home-screen')
</body>
</html>
