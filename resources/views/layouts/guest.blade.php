@extends('layouts.app')
@section("title", "LOGIN")
@php
    // Mesma imagem de fundo editável do hero (home/bg); fallback barbearia.jpeg.
    $loginBg = \App\Models\PageContent::image('home', 'bg', Storage::url('galeria/barbearia.jpeg'));
@endphp
@section('style')
<style>
    .login-screen { position: relative; min-height: 100vh; overflow: hidden; }
    .login-bg { position: absolute; inset: 0; z-index: 0;
        background: url("{{ $loginBg }}") center/cover no-repeat;
        filter: blur(12px) brightness(.30); transform: scale(1.12); }
    .login-veil { position: absolute; inset: 0; z-index: 1;
        background: radial-gradient(ellipse at center, rgba(10,8,5,.45), rgba(5,4,2,.88)); }
    .login-content { position: relative; z-index: 2; }
    .login-logo { filter: drop-shadow(0 8px 18px rgba(0,0,0,.45)); }
    .login-card { background: linear-gradient(155deg, #f1dc8e 0%, #e2c060 100%) !important; box-shadow: 0 16px 40px rgba(0,0,0,.45) !important; }
    .login-card button[type="submit"] { background-color: #1a1410 !important; border-color: #1a1410 !important; color: #f3e6c4 !important; }
    .login-card button[type="submit"]:hover { background-color: #000 !important; border-color: #000 !important; }
    /* Textos pretos sobre o card dourado. Força TODO texto dentro do card (inclusive
       placeholder) para preto; o botão de submit mantém texto creme pela regra específica. */
    .login-card, .login-card *, .login-card input::placeholder, .login-card textarea::placeholder { color: #000 !important; }
</style>
@endsection
@section('main')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div class="login-screen">
        <div class="login-bg"></div>
        <div class="login-veil"></div>

        <div class="login-content min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="text-center my-4 login-logo">
                <a href="/" style="text-decoration:none;">
                    <img src="{{ \App\Models\PageContent::image('branding', 'logo', Storage::url('logo/logo-navbar.jpeg')) }}" alt="Barbearia Frioli" style="max-width: 180px; width: 50vw; border-radius: 12px;">
                </a>
            </div>

            <div class="login-card w-full sm:max-w-md mt-6 px-6 py-4 overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </div>
@endsection
