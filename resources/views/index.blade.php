@extends("layouts.app")
@section("title", "Início")
@php
    use App\Models\GalleryImage;
    use App\Models\PageContent;
    $fotos = GalleryImage::orderBy('ordem')->orderBy('id')->get();
    $urls  = $fotos->map(fn($f) => '/storage/galeria/' . $f->filename)->all();
    $carrossel = array_merge($urls, $urls); // duplicado p/ loop infinito suave

    // Imagens editáveis pelo /admin (tipo image). Fallback = arquivo fixo de storage.
    $heroBg    = PageContent::image('home',  'bg',   Storage::url('galeria/barbearia.jpeg'));
    $heroLogo  = PageContent::image('home',  'logo', Storage::url('logo/logo-claro.jpeg'));
    // Fundo texturizado da página inteira (arquivo direto em storage/public; sem blur, só cover).
    $fundoBg   = Storage::url('fundo.png');
    $sobreFoto = PageContent::image('sobre', 'foto', Storage::url('galeria/perfil.jpeg'));
    $cardFoto  = [
        1 => PageContent::image('servicos', 'card1_foto', ''),
        2 => PageContent::image('servicos', 'card2_foto', ''),
        3 => PageContent::image('servicos', 'card3_foto', ''),
    ];

    // Endereço (editável em /admin) — alimenta a seção "Como chegar" e o iframe do mapa.
    $endRua  = PageContent::get('endereco', 'rua', '');
    $mapaSrc = $endRua !== '' ? 'https://maps.google.com/maps?q=' . urlencode($endRua) . '&z=16&output=embed' : '';
@endphp
@section("style")
    {{-- Pré-carrega os primeiros itens do carrossel (hero) --}}
    @foreach(array_slice($urls, 0, 3) as $u)
        <link rel="preload" as="image" href="{{ $u }}">
    @endforeach
    <link rel="preload" as="image" href="{{ $heroBg }}">
    <link rel="preload" as="image" href="{{ $fundoBg }}">
@endsection
@section("main")
    <style>
        main { overflow-x: hidden; }

        /* ── Fundo texturizado: cover em página inteira, sem blur, fixo ao rolar ── */
        body { background-color: #15110b; }
        body::before {
            content: "";
            position: fixed; inset: 0; z-index: -1;
            /* Véu escuro sobre a textura (sem blur): deixa o fundo dark e a textura ainda aparece. */
            background:
                linear-gradient(rgba(10,8,5,.50), rgba(10,8,5,.50)),
                url("{{ $fundoBg }}") center/cover no-repeat;
        }
        /* Película escura translúcida nas seções: a textura (já escurecida) aparece
           por baixo e o texto claro (tema dark) continua legível. Aumente o último
           número p/ mais contraste; diminua p/ ver mais a textura. */
        main > section { background-color: rgba(16,13,8,.60); }

        /* ── Faixa Serviços → Agendar: card full-width, parallax, inner shadow, linha dourada ── */
        .faixa-servicos {
            position: relative;
            width: 100%;
            overflow: hidden;
            background-color: transparent; /* sobrepõe o véu default de main>section */
            border-bottom: 3px solid var(--marrom); /* linha dourada na parte de baixo */
        }
        .faixa-bg {
            position: absolute; left: 0; right: 0; top: -15%; height: 130%; z-index: 0;
            background: url("{{ $heroBg }}") center/cover no-repeat;
            will-change: transform; /* parallax via JS: translate + scale (zoom p/ mover sem borda) */
        }
        .faixa-overlay {
            position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(180deg, rgba(16,13,8,.82), rgba(16,13,8,.68)); /* véu mais leve p/ ver a barbearia deslizando no fundo */
            /* inner shadow (inset) nas bordas → profundidade de card */
            box-shadow:
                inset 0 55px 60px -30px rgba(0,0,0,.95),
                inset 0 -55px 60px -30px rgba(0,0,0,.95),
                inset 70px 0 90px -70px rgba(0,0,0,.85),
                inset -70px 0 90px -70px rgba(0,0,0,.85);
        }
        .faixa-conteudo { position: relative; z-index: 2; }
        /* parallax do .faixa-bg é feito em JS (translate + scale); funciona igual em desktop e mobile */

        /* ── Hero ─────────────────────────────────────────────────────── */
        .hero {
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1a1813;
        }
        .hero-bg {
            position: absolute; inset: 0; z-index: 0;
            background: url("{{ $heroBg }}") center/cover no-repeat;
            filter: blur(16px) brightness(.40);
            transform: scale(1.18);
        }
        .hero-bg-tint { position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(180deg, rgba(26,24,19,.55), rgba(26,24,19,.80)); }

        .hero-carousel {
            position: absolute; inset: 0; z-index: 2;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            -webkit-mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
                    mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
        }
        .hero-rotator { transform: rotate(-20deg) scale(1.75); display: flex; align-items: center; }
        .hero-track {
            display: flex; gap: 1.25rem; align-items: center;
            animation: heroScroll 224s linear infinite;
            will-change: transform;
        }
        .hero-track img {
            height: 60vh; width: 38vh; flex: 0 0 auto;
            object-fit: cover; border-radius: 14px;
            box-shadow: 0 18px 40px rgba(0,0,0,.55);
            background: #1a1813;
        }
        @keyframes heroScroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        .hero-dim { position: absolute; inset: 0; z-index: 3;
            background: radial-gradient(ellipse at center, rgba(26,24,19,.35) 0%, rgba(26,24,19,.72) 100%); }
        .hero-content { position: relative; z-index: 4; text-align: center; padding: 0 1rem; }
        .hero-logo { height: 260px; width: auto; border-radius: 16px; filter: drop-shadow(0 10px 24px rgba(0,0,0,.6)); }

        @media (max-width: 768px) {
            .hero-rotator { transform: rotate(-20deg) scale(2.3); }
            .hero-track img { height: 48vh; width: 31vh; }
            .hero-logo { height: 190px; }
        }

        .btn-agendar, .btn-contato { background-color: var(--marrom); border-color: var(--marrom); color: #1a1410; }
        .servico-card { border: 1px solid rgba(201,163,111,.30); border-radius: .75rem; background-color: #1d1810; }
        .galeria-grid { columns: 3; column-gap: 1rem; }
        .galeria-grid img { width: 100%; margin-bottom: 1rem; border-radius: .75rem; box-shadow: 0 6px 18px rgba(0,0,0,.18); break-inside: avoid; }
        @media (max-width: 768px) { .galeria-grid { columns: 2; } }
        @media (max-width: 480px) { .galeria-grid { columns: 1; } }
    </style>

    <!-- FRIOLI-BUILD: carrossel=224s (desktop e mobile), angulo=-20deg (edite o CSS acima) -->
    <!-- ── Hero ─────────────────────────────────────────────────────── -->
    <header class="hero text-white">
        <div class="hero-bg"></div>
        <div class="hero-bg-tint"></div>

        @if(!empty($carrossel))
        <div class="hero-carousel">
            <div class="hero-rotator">
                <div class="hero-track">
                    @foreach($carrossel as $c)
                        <img src="{{ $c }}" alt="Corte Barbearia Frioli" decoding="async">
                    @endforeach
                </div>
            </div>
        </div>
        <div class="hero-dim"></div>
        @endif

        <div class="hero-content container">
            <img class="hero-logo mb-3" src="{{ $heroLogo }}" alt="Barbearia Frioli">
            <p class="lead mb-4">{!! PageContent::get('home', 'tagline', 'Estilo, precisão e atendimento de respeito.') !!}</p>
            <a href="#endereco" class="btn btn-agendar mt-2 px-4">{!! PageContent::get('endereco', 'cta', 'Como chegar') !!}</a>
        </div>
    </header>

    <!-- ── Sobre ────────────────────────────────────────────────────── -->
    <section id="sobre" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 mb-4 mb-md-0 d-flex justify-content-center">
                    <div class="ratio ratio-1x1" style="width: 100%; max-width: 340px;">
                        <img src="{{ $sobreFoto }}" class="rounded shadow" style="object-fit: cover;" alt="Barbeiro Frioli" loading="lazy">
                    </div>
                </div>
                <div class="col-md-6">
                    <p class="fs-3" style="color: var(--marrom);">{!! PageContent::get('sobre', 'titulo', 'Sobre nós') !!}</p>
                    <p>{!! PageContent::get('sobre', 'texto', '') !!}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ── Faixa Serviços → Agendar (full-width, parallax, inner shadow, linha dourada) ── -->
    <section id="servicos" class="faixa-servicos">
        <div class="faixa-bg" aria-hidden="true"></div>
        <div class="faixa-overlay" aria-hidden="true"></div>
        <div class="container text-center faixa-conteudo py-5">
            <p class="mb-4 fs-2" style="color: var(--marrom);">{!! PageContent::get('servicos', 'titulo', 'Serviços') !!}</p>
            <div class="row g-3">
                @foreach([1,2,3] as $i)
                    <div class="col-md-4">
                        <div class="card servico-card shadow-sm h-100 overflow-hidden">
                            @if(!empty($cardFoto[$i]))
                                <img src="{{ $cardFoto[$i] }}" class="card-img-top" alt="{{ PageContent::get('servicos', "card{$i}_titulo", 'Serviço') }}" style="height:170px; object-fit:cover" loading="lazy">
                            @endif
                            <div class="p-3">
                                <h5 class="card-title fs-5">{!! PageContent::get('servicos', "card{$i}_titulo", '') !!}</h5>
                                <p class="card-text">{!! PageContent::get('servicos', "card{$i}_desc", '') !!}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-secondary">{!! PageContent::get('servicos', 'nota', '') !!}</p>
            <a href="{{ route('agendar.entrar') }}" class="btn btn-agendar btn-lg px-5 fw-semibold mt-3">Agende seu corte agora</a>
        </div>
    </section>

    <!-- ── Endereço / Como chegar ─────────────────────────────────── -->
    <section id="endereco" class="py-5">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-md-5">
                    <p class="fs-3 mb-2" style="color: var(--marrom);">{!! PageContent::get('endereco', 'titulo', 'Onde estamos') !!}</p>
                    <p class="mb-3">{!! nl2br(e($endRua)) !!}</p>
                    <a href="{{ PageContent::get('endereco', 'link', '#') }}" target="_blank" rel="noopener" class="btn btn-agendar px-4">📍 Como chegar</a>
                </div>
                <div class="col-md-7">
                    @if($mapaSrc !== '')
                    <div class="ratio ratio-4x3 rounded-3 overflow-hidden shadow-sm">
                        <iframe src="{{ $mapaSrc }}" style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mapa — Barbearia Frioli"></iframe>
                    </div>
                    @else
                    <p class="text-secondary">Mapa indisponível — cadastre o endereço no painel.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- ── Galeria / Nossa casa ──────────────────────────────────────── -->
    <section id="galeria" class="py-5">
        <div class="container">
            <p class="mb-4 fs-2 text-center" style="color: var(--marrom);">Nossa casa</p>
            @if($fotos->isNotEmpty())
            <div class="galeria-grid">
                @foreach($fotos as $foto)
                    <img src="/storage/galeria/{{ $foto->filename }}" alt="{{ $foto->title ?? 'Barbearia Frioli' }}" loading="lazy">
                @endforeach
            </div>
            @else
            <p class="text-center text-secondary">Em breve novas fotos por aqui.</p>
            @endif
        </div>
    </section>

    <!-- ── Contato ──────────────────────────────────────────────────── -->
    <section id="contato" class="py-5">
        <div class="container text-center">
            <h2 class="mb-2 fs-2" style="color: var(--marrom);">{!! PageContent::get('contato', 'titulo', 'Entre em Contato') !!}</h2>
            <p class="mb-3">{!! PageContent::get('contato', 'subtitulo', 'Agende seu horário ou tire suas dúvidas:') !!}</p>
            <a href="https://wa.me/{{ PageContent::get('contato', 'whatsapp_numero', '5511988245815') }}" target="_blank" class="btn btn-contato px-4">{!! PageContent::get('contato', 'whatsapp_label', 'Falar no WhatsApp') !!}</a>
        </div>
    </section>

    {{-- Parallax do fundo da faixa Serviços: a imagem da barbearia desliza no fundo ao rolar. --}}
    <script>
    (function () {
        const bg = document.querySelector('.faixa-servicos .faixa-bg');
        if (!bg) return;
        const section = bg.closest('.faixa-servicos') || bg.parentElement;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let ticking = false;

        function update() {
            const r = section.getBoundingClientRect();
            const vh = window.innerHeight || document.documentElement.clientHeight;
            // progresso de -1 (entrando por baixo) a +1 (saindo por cima)
            const progress = (r.top + r.height / 2 - vh / 2) / ((vh + r.height) / 2);
            const p = Math.max(-1, Math.min(1, progress));
            const shift = -p * 10; // ±10% — mais lento que o conteúdo = parallax
            bg.style.transform = 'translate3d(0,' + shift + '%,0) scale(1.08)'; // scale = zoom p/ não aparecer borda
            ticking = false;
        }

        if (reduce || !window.matchMedia('(min-width: 769px)').matches) {
            // mobile ou reduced-motion: fundo estático (parallax só no desktop)
            bg.style.transform = 'scale(1.08)';
        } else {
            const onScroll = function () { if (!ticking) { requestAnimationFrame(update); ticking = true; } };
            window.addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onScroll, { passive: true });
            update();
        }
    })();
    </script>
@endsection
