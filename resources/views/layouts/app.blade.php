<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" /> 
        <meta name="theme-color" content="#161310">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="msapplication-navbutton-color" content="#161310">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}?v={{time()}}">
        <link rel="icon" href="{{ asset('favicon.ico') }}?v={{time()}}" type="image/x-icon">
        <link rel="icon" href="{{ asset('favicon.png') }}?v={{time()}}" type="image/png">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery-mask-plugin@1.14.16/dist/jquery.mask.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <title>@yield("title") - Barbearia Frioli</title>
        @yield("style")
        @vite(['resources/js/app.js'])
        @yield("scriptTop")
        <style>
            :root {
                /* Paleta da logo: dourado sobre preto-quente */
                --marrom: #c9a36f;   /* dourado — títulos, acentos, botões */
                --bege:   #e6c98a;   /* dourado claro — destaques */
                --branco: #161310;   /* fundo principal (preto-quente da logo) */
                --cinza:  #9a8d76;   /* texto muted */
                --escuro: #0e0c08;   /* mais escuro ainda */
            }
            /* Bootstrap 5.3 dark mode na paleta da logo */
            [data-bs-theme="dark"] {
                --bs-body-bg: #161310;
                --bs-body-color: #ece4d6;
                --bs-heading-color: #ece4d6;
                --bs-emphasis-color: #fbf6ec;
                --bs-secondary-color: #b6a98e;
                --bs-secondary-bg: #211c14;
                --bs-tertiary-bg: #1d1810;   /* cards, inputs, painéis */
                --bs-tertiary-color: #cdbfa3;
                --bs-primary: #c9a36f;
                --bs-primary-rgb: 201, 163, 111;
                --bs-border-color: #3a3127;
                --bs-link-color: #d9b978;
                --bs-link-hover-color: #e8cd96;
                --bs-card-bg: #1d1810;
                --bs-list-group-bg: #1d1810;
            }
            /* utilidades Bootstrap hardcoded que brigam com o dark */
            [data-bs-theme="dark"] .text-dark { color: var(--bs-body-color) !important; }
            [data-bs-theme="dark"] .bg-white,
            [data-bs-theme="dark"] .bg-light { background-color: var(--bs-tertiary-bg) !important; }
            /* botões dourados: texto escuro p/ alto contraste (estilo logo) */
            [data-bs-theme="dark"] .btn[style*="var(--marrom)"] { color: #1a1410 !important; }
            /* btn-primary é azul por padrão no Bootstrap — forçamos o dourado da marca */
            [data-bs-theme="dark"] .btn-primary {
                --bs-btn-bg: #c9a36f; --bs-btn-border-color: #c9a36f;
                --bs-btn-hover-bg: #d9b978; --bs-btn-hover-border-color: #d9b978;
                --bs-btn-active-bg: #b8905a; --bs-btn-active-border-color: #b8905a;
                --bs-btn-disabled-bg: #8a7a5e; --bs-btn-disabled-border-color: #8a7a5e;
                --bs-btn-color: #1a1410; --bs-btn-hover-color: #1a1410; --bs-btn-active-color: #1a1410;
            }
            [data-bs-theme="dark"] .btn-outline-primary {
                --bs-btn-color: #c9a36f; --bs-btn-border-color: #c9a36f;
                --bs-btn-hover-bg: #c9a36f; --bs-btn-hover-border-color: #c9a36f;
                --bs-btn-active-bg: #c9a36f; --bs-btn-active-border-color: #c9a36f;
                --bs-btn-hover-color: #1a1410; --bs-btn-active-color: #1a1410;
            }
            /* Utilidades Tailwind que ignoram o dark (telas de auth do Breeze) */
            [data-bs-theme="dark"] .text-gray-500,
            [data-bs-theme="dark"] .text-gray-600,
            [data-bs-theme="dark"] .text-gray-700,
            [data-bs-theme="dark"] .text-gray-800,
            [data-bs-theme="dark"] .text-gray-900 { color: var(--bs-body-color) !important; }
            [data-bs-theme="dark"] .bg-gray-50,
            [data-bs-theme="dark"] .bg-gray-100,
            [data-bs-theme="dark"] .bg-gray-200 { background-color: transparent !important; }
            /* Botões de submit (bg-gray-800) viram dourado da marca */
            [data-bs-theme="dark"] .bg-gray-800 { background-color: var(--marrom) !important; color: #1a1410 !important; }
            [data-bs-theme="dark"] .hover\:bg-gray-700:hover { background-color: #d9b978 !important; }

            /* ── Mobile (barbeiro usa mais no celular) ───────────────────────── */
            @media (max-width: 768px) {
                /* Tabelas: scroll horizontal + fonte menor */
                .table { font-size: 0.82rem; }
                .table th, .table td { white-space: nowrap; padding: 0.4rem 0.5rem; }
                /* Wrap de tabelas sem table-responsive */
                .card-body > table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                /* Modais: full height aproveitando a tela */
                .modal-dialog { margin: 0.5rem; }
                .modal-body { max-height: 70vh; }
                /* Botões de ação nos cards: quebram linha se preciso */
                .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.8rem; }
                /* Inputs: font-size 16px evita zoom do iOS */
                .form-control, .form-select { font-size: 1rem; }
            }
        </style>
    </head>
    <body>
        @include("layouts.nav")
        <main>
            @include('components.msg')
            @yield("main")
        </main>
        <footer>
            @yield("footer")
            <footer class="bg-dark text-white text-center" style="bottom:0px;position:fixed;width:100vw;">
                <p class="mb-0" style="font-size: 10px;">&copy; {{date("Y")}} - Todos os direitos reservados - By <a href="https://evtu.com.br" target="_blank">EVTU</a></p>
            </footer>
        </footer>
        @yield("scriptEnd")
        <script>
            $(function () {
                $('[name="whatsapp"], [name="whatsapp_edt"]').mask('+55 (00) 00000-0000');
            });
        </script>
        <script>
            // Example starter JavaScript for disabling form submissions if there are invalid fields
            (() => {
            'use strict'

            // Fetch all the forms we want to apply custom Bootstrap validation styles to
            const forms = document.querySelectorAll('.needs-validation')

            // Loop over them and prevent submission
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }

                form.classList.add('was-validated')
                }, false)
            })
            })()
        </script>
    </body>
</html>
