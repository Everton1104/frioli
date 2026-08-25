@extends('layouts.app')
@section("title", "Frioli")
@php
    // Fundo + logo da marca (fixos da referência; não usam as chaves antigas
    // branding.logo/home.bg, que podem ter valores velhos em produção).
    $guestBg   = Storage::url('site/hero.jpg');
    $guestLogo = Storage::url('site/logo-frioli.png');
@endphp
@section('style')
    <link rel="stylesheet" href="{{ asset('css/frioli-guest.css') }}?v={{ time() }}">
    <style>
        body { background: #0F0D0B; }
        /* Esconde a topbar global SÓ nestas telas de card (login/OTP/entrar).
           A logo acima do card leva de volta à home. */
        .nav { display: none !important; }
    </style>
@endsection
@section('main')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div class="fg">
        <div class="fg-bg" style="background-image:url('{{ $guestBg }}')"></div>
        <div class="fg-veil"></div>
        <div class="fg-grain"></div>

        <a class="fg-logo" href="/" aria-label="Frioli Hair, início">
            <img src="{{ $guestLogo }}" alt="Barbearia Frioli Hair">
        </a>

        <div class="fg-card">
            {{ $slot }}
        </div>
    </div>
@endsection
