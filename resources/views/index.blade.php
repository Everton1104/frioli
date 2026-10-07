@extends("layouts.app")
@section("title", "Barbearia Frioli — Jundiaí desde 2015")
@php
    use App\Models\PageContent;

    // Fotos editáveis (chaves novas, type=image, value vazio → fallback em site/).
    // home.hero = fundo do hero (foto da casa, desfocada atrás do logo).
    $hero     = PageContent::image('home', 'hero', Storage::url('site/interior.jpg'));
    // fixo da marca; ?v=filemtime busta o cache do Cloudflare quando o arquivo troca
    $logo     = Storage::url('site/logo-frioli.png').'?v='.filemtime(Storage::disk('public')->path('site/logo-frioli.png'));
    $interior = PageContent::image('sobre', 'interior', Storage::url('site/interior.jpg'));
    $salao    = PageContent::image('espaco', 'salao', Storage::url('site/salao.jpg'));
    $cadeira  = PageContent::image('espaco', 'cadeira', Storage::url('site/salao-cadeira.jpg'));
    $toalha   = PageContent::image('espaco', 'toalha', Storage::url('site/salao-toalha.jpg'));
    $frioli   = PageContent::image('barbeiros', 'b1_foto', Storage::url('site/frioli.jpg'));
    $tiago    = PageContent::image('barbeiros', 'b2_foto', Storage::url('site/tiago.jpg'));
    $cortes   = [
        PageContent::image('trabalhos', 'g1', Storage::url('site/corte1.jpg')),
        PageContent::image('trabalhos', 'g2', Storage::url('site/corte2.jpg')),
        PageContent::image('trabalhos', 'g3', Storage::url('site/corte5.jpg')),
        PageContent::image('trabalhos', 'g4', Storage::url('site/corte4.jpg')),
    ];

    // CTA de agendamento roteia pelo estado de login/verificação.
    $u = auth()->user();
    $rotaAgendar = !$u ? route('agendar.entrar')
        : ($u->whatsappVerificado() ? route('agendar.index') : route('verificar.whatsapp'));

    $endRua  = PageContent::get('endereco', 'rua', 'Rua do Retiro, 329, Jundiaí, SP');
    $mapaSrc = 'https://www.google.com/maps?q=' . urlencode($endRua) . '&output=embed';
    $wa      = preg_replace('/\D/', '', PageContent::get('contato', 'whatsapp_numero', '5511981869528'));
    $waLink  = 'https://wa.me/' . $wa . '?text=' . urlencode('Fala Frioli! Quero marcar um horário.');
    $ig      = PageContent::def('local', 'instagram');
    $igUrl   = 'https://instagram.com/' . ltrim($ig, '@');

    // Listas a partir do catálogo editável (config/content).
    $servicos = [];
    for ($i = 1; $i <= 6; $i++) {
        $servicos[] = [
            PageContent::def('servicos', "s{$i}_nome"),
            PageContent::def('servicos', "s{$i}_desc"),
            PageContent::def('servicos', "s{$i}_preco"),
            $i === 2, // destaque (Corte + Barba)
        ];
    }
    $reviews = [];
    for ($i = 1; $i <= 5; $i++) {
        $reviews[] = [PageContent::def('avaliacoes', "r{$i}_t"), PageContent::def('avaliacoes', "r{$i}_a")];
    }
    $tags1 = array_filter(array_map('trim', explode(',', PageContent::def('barbeiros', 'b1_tags'))));
    $tags2 = array_filter(array_map('trim', explode(',', PageContent::def('barbeiros', 'b2_tags'))));
@endphp
@section("style")
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;0,800;0,900;1,500&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="{{ $hero }}">
    <link rel="stylesheet" href="{{ asset('css/frioli-home.css') }}?v={{ time() }}">
@endsection
@section("main")
    <div class="fh">

        <nav class="fh-nav">
            <div class="wrap">
                <a class="marca" href="#topo" aria-label="Frioli Hair, início">
                    <img src="{{ $logo }}" alt="Barbearia Frioli Hair, desde 2015">
                </a>
                <div class="navlinks">
                    <a href="#sobre">A barbearia</a>
                    <a href="#ritual">Como funciona</a>
                    <a href="#servicos">Serviços</a>
                    <a href="#barbeiros">Barbeiros</a>
                    <a href="#avaliacoes">Avaliações</a>
                    <a href="#local">Onde ficamos</a>
                    @guest
                        <a class="auth" href="{{ url('/login') }}">Entrar</a>
                    @endguest
                    @auth
                        <a class="auth" href="{{ url('/dashboard') }}">Painel</a>
                        @if(auth()->user()->adm)<a class="auth" href="{{ url('/admin') }}">Admin</a>@endif
                        <a class="auth" href="#" onclick="document.getElementById('fh-logout').submit();return false;">Sair</a>
                    @endauth
                    <a class="btn-ouro" href="{{ $rotaAgendar }}">Agendar</a>
                </div>
            </div>
        </nav>

        {{-- HERO — logo central sobre foto da casa desfocada --}}
        <header class="hero" id="topo">
            <div class="hero-fundo" aria-hidden="true">
                <img src="{{ $hero }}" alt="" fetchpriority="high">
                <div class="hero-veu"></div>
                <div class="grao"></div>
            </div>

            <div class="hero-texto">
                <div class="hero-conteudo">
                    <div class="hero-logo">
                        <img src="{{ $logo }}" alt="Barbearia Frioli Hair — desde 2015" width="462" height="520">
                    </div>
                    <h1>{!! PageContent::def('hero', 'headline') !!}</h1>
                    <p class="lede">{!! PageContent::def('hero', 'lede') !!}</p>
                    <div class="hero-cta">
                        <a class="btn-ouro" href="{{ $rotaAgendar }}">Agendar horário</a>
                        <a class="btn-linha" href="{{ $waLink }}" target="_blank" rel="noopener">Chamar no WhatsApp</a>
                    </div>
                </div>
            </div>

            <div class="hero-cortina"></div>
            <div class="rolar">role<span></span></div>
        </header>

        {{-- RÉGUA DE BARBEIRO (stats) --}}
        <section class="sec-carvao regua-bloco" id="prova">
            <div class="wrap">
                <div class="reveal">
                    <span class="eyebrow">{!! PageContent::def('prova', 'eyebrow') !!}</span>
                    <hr class="regua">
                    <h2>{!! PageContent::def('prova', 'titulo') !!}</h2>
                    <p class="lede">{!! PageContent::def('prova', 'lede') !!}</p>
                </div>

                <div class="regua-palco reveal">
                    <svg class="regua-svg" viewBox="0 0 1000 118" role="img" aria-label="Régua de barbeiro graduada de zero a quatro, com os números reais da casa" preserveAspectRatio="xMidYMid meet">
                        <defs>
                            <linearGradient id="rg-metal" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0"    stop-color="#6a6252"/>
                                <stop offset=".07"  stop-color="#c2b69d"/>
                                <stop offset=".30"  stop-color="#6f6757"/>
                                <stop offset=".54"  stop-color="#423c31"/>
                                <stop offset=".80"  stop-color="#7d7462"/>
                                <stop offset="1"    stop-color="#2a251d"/>
                            </linearGradient>
                            <pattern id="rg-escova" width="7" height="3" patternUnits="userSpaceOnUse">
                                <line x1="0" y1=".5" x2="7" y2=".5" stroke="#fff" stroke-opacity=".055" stroke-width=".6"/>
                                <line x1="0" y1="2"  x2="7" y2="2"  stroke="#000" stroke-opacity=".07"  stroke-width=".6"/>
                            </pattern>
                            <linearGradient id="rg-varre" x1="0" y1="0" x2="1" y2="0">
                                <stop offset="0"   stop-color="#E4CF92" stop-opacity="0"/>
                                <stop offset=".5"  stop-color="#E4CF92" stop-opacity=".46"/>
                                <stop offset="1"   stop-color="#E4CF92" stop-opacity="0"/>
                            </linearGradient>
                            <clipPath id="rg-corpo"><rect x="14" y="18" width="972" height="84" rx="5"/></clipPath>
                            <filter id="rg-sombra" x="-6%" y="-60%" width="112%" height="260%">
                                <feDropShadow dx="0" dy="10" stdDeviation="12" flood-color="#000" flood-opacity=".62"/>
                            </filter>
                        </defs>
                        <g filter="url(#rg-sombra)">
                            <rect x="14" y="18" width="972" height="84" rx="5" fill="url(#rg-metal)"/>
                            <rect x="14" y="18" width="972" height="84" rx="5" fill="url(#rg-escova)"/>
                        </g>
                        <g clip-path="url(#rg-corpo)">
                            <g stroke="#14110d" stroke-opacity=".82" stroke-linecap="butt">
                                <path d="M25 18v14" stroke-width="1.3"/> <path d="M50 18v24" stroke-width="1.8"/> <path d="M75 18v14" stroke-width="1.3"/> <path d="M100 18v36" stroke-width="2.4"/> <path d="M125 18v14" stroke-width="1.3"/> <path d="M150 18v24" stroke-width="1.8"/>
                                <path d="M175 18v14" stroke-width="1.3"/> <path d="M200 18v24" stroke-width="1.8"/> <path d="M225 18v14" stroke-width="1.3"/> <path d="M250 18v24" stroke-width="1.8"/> <path d="M275 18v14" stroke-width="1.3"/> <path d="M300 18v36" stroke-width="2.4"/>
                                <path d="M325 18v14" stroke-width="1.3"/> <path d="M350 18v24" stroke-width="1.8"/> <path d="M375 18v14" stroke-width="1.3"/> <path d="M400 18v24" stroke-width="1.8"/> <path d="M425 18v14" stroke-width="1.3"/> <path d="M450 18v24" stroke-width="1.8"/>
                                <path d="M475 18v14" stroke-width="1.3"/> <path d="M500 18v36" stroke-width="2.4"/> <path d="M525 18v14" stroke-width="1.3"/> <path d="M550 18v24" stroke-width="1.8"/> <path d="M575 18v14" stroke-width="1.3"/> <path d="M600 18v24" stroke-width="1.8"/>
                                <path d="M625 18v14" stroke-width="1.3"/> <path d="M650 18v24" stroke-width="1.8"/> <path d="M675 18v14" stroke-width="1.3"/> <path d="M700 18v36" stroke-width="2.4"/> <path d="M725 18v14" stroke-width="1.3"/> <path d="M750 18v24" stroke-width="1.8"/>
                                <path d="M775 18v14" stroke-width="1.3"/> <path d="M800 18v24" stroke-width="1.8"/> <path d="M825 18v14" stroke-width="1.3"/> <path d="M850 18v24" stroke-width="1.8"/> <path d="M875 18v14" stroke-width="1.3"/> <path d="M900 18v36" stroke-width="2.4"/>
                                <path d="M925 18v14" stroke-width="1.3"/> <path d="M950 18v24" stroke-width="1.8"/> <path d="M975 18v14" stroke-width="1.3"/>
                            </g>
                            <g stroke="#e8dfc9" stroke-opacity=".22" stroke-linecap="butt" transform="translate(1.1,0)">
                                <path d="M25 18v14" stroke-width=".7"/> <path d="M50 18v24" stroke-width=".9"/> <path d="M75 18v14" stroke-width=".7"/> <path d="M100 18v36" stroke-width="1.1"/> <path d="M125 18v14" stroke-width=".7"/> <path d="M150 18v24" stroke-width=".9"/>
                                <path d="M175 18v14" stroke-width=".7"/> <path d="M200 18v24" stroke-width=".9"/> <path d="M225 18v14" stroke-width=".7"/> <path d="M250 18v24" stroke-width=".9"/> <path d="M275 18v14" stroke-width=".7"/> <path d="M300 18v36" stroke-width="1.1"/>
                                <path d="M325 18v14" stroke-width=".7"/> <path d="M350 18v24" stroke-width=".9"/> <path d="M375 18v14" stroke-width=".7"/> <path d="M400 18v24" stroke-width=".9"/> <path d="M425 18v14" stroke-width=".7"/> <path d="M450 18v24" stroke-width=".9"/>
                                <path d="M475 18v14" stroke-width=".7"/> <path d="M500 18v36" stroke-width="1.1"/> <path d="M525 18v14" stroke-width=".7"/> <path d="M550 18v24" stroke-width=".9"/> <path d="M575 18v14" stroke-width=".7"/> <path d="M600 18v24" stroke-width=".9"/>
                                <path d="M625 18v14" stroke-width=".7"/> <path d="M650 18v24" stroke-width=".9"/> <path d="M675 18v14" stroke-width=".7"/> <path d="M700 18v36" stroke-width="1.1"/> <path d="M725 18v14" stroke-width=".7"/> <path d="M750 18v24" stroke-width=".9"/>
                                <path d="M775 18v14" stroke-width=".7"/> <path d="M800 18v24" stroke-width=".9"/> <path d="M825 18v14" stroke-width=".7"/> <path d="M850 18v24" stroke-width=".9"/> <path d="M875 18v14" stroke-width=".7"/> <path d="M900 18v36" stroke-width="1.1"/>
                                <path d="M925 18v14" stroke-width=".7"/> <path d="M950 18v24" stroke-width=".9"/> <path d="M975 18v14" stroke-width=".7"/>
                            </g>
                            <g font-family="Inter, -apple-system, sans-serif" font-weight="700" font-size="31" text-anchor="middle" style="font-variant-numeric:tabular-nums">
                                <g fill="#e8dfc9" fill-opacity=".20" transform="translate(0,1.3)">
                                    <text x="100" y="88">0</text><text x="300" y="88">1</text><text x="500" y="88">2</text><text x="700" y="88">3</text><text x="900" y="88">4</text>
                                </g>
                                <g fill="#14110d" fill-opacity=".78">
                                    <text x="100" y="88">0</text><text x="300" y="88">1</text><text x="500" y="88">2</text><text x="700" y="88">3</text><text x="900" y="88">4</text>
                                </g>
                            </g>
                            <rect id="varredura-luz" x="0" y="18" width="150" height="84" fill="url(#rg-varre)" style="mix-blend-mode:screen"/>
                            <g stroke="#F4E7BE" stroke-linecap="butt" style="mix-blend-mode:screen">
                                <path class="dente" data-x="25" d="M25 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="50" d="M50 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="75" d="M75 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="100" d="M100 18v36" stroke-width="2.4" opacity="0"/> <path class="dente" data-x="125" d="M125 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="150" d="M150 18v24" stroke-width="1.8" opacity="0"/>
                                <path class="dente" data-x="175" d="M175 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="200" d="M200 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="225" d="M225 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="250" d="M250 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="275" d="M275 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="300" d="M300 18v36" stroke-width="2.4" opacity="0"/>
                                <path class="dente" data-x="325" d="M325 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="350" d="M350 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="375" d="M375 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="400" d="M400 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="425" d="M425 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="450" d="M450 18v24" stroke-width="1.8" opacity="0"/>
                                <path class="dente" data-x="475" d="M475 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="500" d="M500 18v36" stroke-width="2.4" opacity="0"/> <path class="dente" data-x="525" d="M525 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="550" d="M550 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="575" d="M575 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="600" d="M600 18v24" stroke-width="1.8" opacity="0"/>
                                <path class="dente" data-x="625" d="M625 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="650" d="M650 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="675" d="M675 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="700" d="M700 18v36" stroke-width="2.4" opacity="0"/> <path class="dente" data-x="725" d="M725 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="750" d="M750 18v24" stroke-width="1.8" opacity="0"/>
                                <path class="dente" data-x="775" d="M775 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="800" d="M800 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="825" d="M825 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="850" d="M850 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="875" d="M875 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="900" d="M900 18v36" stroke-width="2.4" opacity="0"/>
                                <path class="dente" data-x="925" d="M925 18v14" stroke-width="1.3" opacity="0"/> <path class="dente" data-x="950" d="M950 18v24" stroke-width="1.8" opacity="0"/> <path class="dente" data-x="975" d="M975 18v14" stroke-width="1.3" opacity="0"/>
                            </g>
                            <path d="M16 19.2H984" stroke="#efe6d0" stroke-opacity=".34" stroke-width="1.2"/>
                            <path d="M16 101H984" stroke="#000" stroke-opacity=".5" stroke-width="1.6"/>
                        </g>
                    </svg>

                    <div class="regua-guias">
                        <div class="guia"><div class="n">{!! PageContent::def('prova', 'n1') !!}</div><div class="rot">{!! PageContent::def('prova', 'r1') !!}</div><div class="val">{!! PageContent::def('prova', 'v1') !!}</div></div>
                        <div class="guia"><div class="n">{!! PageContent::def('prova', 'n2') !!}</div><div class="rot">{!! PageContent::def('prova', 'r2') !!}</div><div class="val">{!! PageContent::def('prova', 'v2') !!}</div></div>
                        <div class="guia"><div class="n">{!! PageContent::def('prova', 'n3') !!}</div><div class="rot">{!! PageContent::def('prova', 'r3') !!}</div><div class="val">{!! PageContent::def('prova', 'v3') !!}</div></div>
                        <div class="guia"><div class="n">{!! PageContent::def('prova', 'n4') !!}</div><div class="rot">{!! PageContent::def('prova', 'r4') !!}</div><div class="val">{!! PageContent::def('prova', 'v4') !!}</div></div>
                        <div class="guia"><div class="n">{!! PageContent::def('prova', 'n5') !!}</div><div class="rot">{!! PageContent::def('prova', 'r5') !!}</div><div class="val">{!! PageContent::def('prova', 'v5') !!}</div></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- SOBRE --}}
        <section id="sobre" class="sec-creme">
            <div class="wrap">
                <div class="split">
                    <div class="reveal">
                        <span class="eyebrow">{!! PageContent::def('sobre', 'eyebrow') !!}</span>
                        <hr class="regua">
                        <h2>{!! PageContent::def('sobre', 'titulo') !!}</h2>
                        {!! PageContent::def('sobre', 'texto') !!}
                    </div>
                    <div class="midia reveal">
                        <img src="{{ $interior }}" alt="Salão da Barbearia Frioli Hair, três cadeiras de couro em Jundiaí" loading="lazy">
                    </div>
                </div>
            </div>
        </section>

        {{-- O ESPAÇO --}}
        <section id="espaco" class="sec-carvao">
            <div class="wrap">
                <div class="reveal">
                    <span class="eyebrow">{!! PageContent::def('espaco', 'eyebrow') !!}</span>
                    <hr class="regua">
                    <h2>{!! PageContent::def('espaco', 'titulo') !!}</h2>
                    <p class="lede">{!! PageContent::def('espaco', 'lede') !!}</p>
                </div>
            </div>
            <figure class="espaco-faixa reveal">
                <img src="{{ $salao }}" alt="Salão da Barbearia Frioli Hair em Jundiaí, com três cadeiras de couro marrom, espelhos e bancada de mármore" loading="lazy">
            </figure>
            <div class="wrap">
                <div class="espaco-duo">
                    <figure class="reveal"><img src="{{ $cadeira }}" alt="Cadeira de barbeiro de couro capitonê na Frioli Hair" loading="lazy"></figure>
                    <figure class="reveal"><img src="{{ $toalha }}" alt="Frioli aplicando toalha quente em cliente na Frioli Hair" loading="lazy"></figure>
                </div>
            </div>
        </section>

        {{-- COMO FUNCIONA / POSTE --}}
        <section id="ritual">
            <div class="wrap">
                <div class="reveal">
                    <span class="eyebrow">{!! PageContent::def('ritual', 'eyebrow') !!}</span>
                    <hr class="regua">
                    <h2>{!! PageContent::def('ritual', 'titulo') !!}</h2>
                    <p class="lede">{!! PageContent::def('ritual', 'lede') !!}</p>
                </div>

                <div class="ritual">
                    <div class="poste-palco reveal">
                        <svg class="poste-svg" viewBox="0 0 120 300" role="img" aria-label="Poste de barbeiro girando com as etapas do atendimento">
                            <defs>
                                <linearGradient id="cromo" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0"   stop-color="#2a251d"/>
                                    <stop offset=".22" stop-color="#a99b82"/>
                                    <stop offset=".48" stop-color="#5d5546"/>
                                    <stop offset=".74" stop-color="#8d8069"/>
                                    <stop offset="1"   stop-color="#221e18"/>
                                </linearGradient>
                                <linearGradient id="vidro" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0"   stop-color="#0F0D0B"/>
                                    <stop offset=".3"  stop-color="#2a251d"/>
                                    <stop offset="1"   stop-color="#0F0D0B"/>
                                </linearGradient>
                                <clipPath id="tubo"><rect x="34" y="52" width="52" height="196" rx="26"/></clipPath>
                                <filter id="sombra-poste" x="-40%" y="-15%" width="180%" height="130%">
                                    <feDropShadow dx="0" dy="7" stdDeviation="9" flood-color="#000" flood-opacity=".65"/>
                                </filter>
                            </defs>
                            <g filter="url(#sombra-poste)">
                                <rect x="22" y="18" width="76" height="30" rx="9" fill="url(#cromo)" stroke="#15120e" stroke-width="1.6"/>
                                <rect x="25" y="21" width="70" height="24" rx="7" fill="none" stroke="#C9A24B" stroke-opacity=".3"/>
                                <rect x="22" y="252" width="76" height="30" rx="9" fill="url(#cromo)" stroke="#15120e" stroke-width="1.6"/>
                                <rect x="25" y="255" width="70" height="24" rx="7" fill="none" stroke="#C9A24B" stroke-opacity=".3"/>
                                <rect x="34" y="52" width="52" height="196" rx="26" fill="url(#vidro)" stroke="#15120e" stroke-width="1.6"/>
                                <g clip-path="url(#tubo)">
                                    <g id="helice">
                                        <g stroke-width="13" stroke-linecap="butt" fill="none">
                                            <path d="M20 300 L100 240" stroke="#C9A24B"/>
                                            <path d="M20 270 L100 210" stroke="#F2EDE3" stroke-opacity=".82"/>
                                            <path d="M20 240 L100 180" stroke="#C9A24B"/>
                                            <path d="M20 210 L100 150" stroke="#F2EDE3" stroke-opacity=".82"/>
                                            <path d="M20 180 L100 120" stroke="#C9A24B"/>
                                            <path d="M20 150 L100  90" stroke="#F2EDE3" stroke-opacity=".82"/>
                                            <path d="M20 120 L100  60" stroke="#C9A24B"/>
                                            <path d="M20  90 L100  30" stroke="#F2EDE3" stroke-opacity=".82"/>
                                            <path d="M20  60 L100   0" stroke="#C9A24B"/>
                                            <path d="M20  30 L100 -30" stroke="#F2EDE3" stroke-opacity=".82"/>
                                            <path d="M20   0 L100 -60" stroke="#C9A24B"/>
                                        </g>
                                    </g>
                                    <rect id="especular" x="40" y="52" width="13" height="196" fill="#F2EDE3" opacity=".18" style="mix-blend-mode:screen"/>
                                    <rect x="34" y="52" width="52" height="196" fill="url(#vidro)" opacity=".45" style="mix-blend-mode:multiply"/>
                                </g>
                            </g>
                        </svg>
                    </div>

                    <div class="etapas reveal">
                        <div class="etapa"><div class="num">01</div><div><h3>{!! PageContent::def('ritual', 'e1_t') !!}</h3><p>{!! PageContent::def('ritual', 'e1_x') !!}</p></div></div>
                        <div class="etapa"><div class="num">02</div><div><h3>{!! PageContent::def('ritual', 'e2_t') !!}</h3><p>{!! PageContent::def('ritual', 'e2_x') !!}</p></div></div>
                        <div class="etapa"><div class="num">03</div><div><h3>{!! PageContent::def('ritual', 'e3_t') !!}</h3><p>{!! PageContent::def('ritual', 'e3_x') !!}</p></div></div>
                        <div class="etapa"><div class="num">04</div><div><h3>{!! PageContent::def('ritual', 'e4_t') !!}</h3><p>{!! PageContent::def('ritual', 'e4_x') !!}</p></div></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- SERVIÇOS --}}
        <section id="servicos" class="sec-creme">
            <div class="wrap">
                <div class="reveal">
                    <span class="eyebrow">{!! PageContent::def('servicos', 'eyebrow') !!}</span>
                    <hr class="regua">
                    <h2>{!! PageContent::def('servicos', 'titulo') !!}</h2>
                </div>
                <div class="servicos">
                    @foreach($servicos as $s)
                        <div class="servico reveal">
                            <span><span class="nome">{{ $s[0] }}</span><span class="desc">{{ $s[1] }}</span></span>
                            @if($s[3])
                                <span class="val"><strong>{{ $s[2] }}</strong></span>
                            @else
                                <span class="val">{{ $s[2] }}</span>
                            @endif
                        </div>
                    @endforeach
                    <div class="servico reveal" style="grid-column:1/-1;border-bottom:none;padding-top:34px">
                        <span><span class="nome">{{ PageContent::def('servicos', 'pac_nome') }}</span><span class="desc">{{ PageContent::def('servicos', 'pac_desc') }}</span></span>
                        <span class="val"><strong>{{ PageContent::def('servicos', 'pac_preco') }}</strong> /mês</span>
                    </div>
                </div>
                <p class="nota reveal">{!! PageContent::def('servicos', 'nota') !!}</p>
            </div>
        </section>

        {{-- BARBEIROS --}}
        <section id="barbeiros">
            <div class="wrap">
                <div class="reveal">
                    <span class="eyebrow">{!! PageContent::def('barbeiros', 'eyebrow') !!}</span>
                    <hr class="regua">
                    <h2>{!! PageContent::def('barbeiros', 'titulo') !!}</h2>
                </div>
                <div class="barbeiros">
                    <article class="barbeiro reveal">
                        <img class="retrato" src="{{ $frioli }}" alt="Frioli, dono e barbeiro da Frioli Hair" loading="lazy">
                        <div class="barbeiro-scrim"></div>
                        <div class="barbeiro-info sx-glass">
                            <span class="papel">{{ PageContent::def('barbeiros', 'b1_papel') }}</span>
                            <h3>{{ PageContent::def('barbeiros', 'b1_nome') }}</h3>
                            <p>{{ PageContent::def('barbeiros', 'b1_bio') }}</p>
                            <div class="tags">
                                @foreach($tags1 as $tag)<span class="tag sx-glass"><span>{{ $tag }}</span></span>@endforeach
                            </div>
                        </div>
                    </article>
                    <article class="barbeiro reveal">
                        <img class="retrato" src="{{ $tiago }}" alt="Tiago, barbeiro da Frioli Hair" loading="lazy">
                        <div class="barbeiro-scrim"></div>
                        <div class="barbeiro-info sx-glass">
                            <span class="papel">{{ PageContent::def('barbeiros', 'b2_papel') }}</span>
                            <h3>{{ PageContent::def('barbeiros', 'b2_nome') }}</h3>
                            <p>{{ PageContent::def('barbeiros', 'b2_bio') }}</p>
                            <div class="tags">
                                @foreach($tags2 as $tag)<span class="tag sx-glass"><span>{{ $tag }}</span></span>@endforeach
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        {{-- TRABALHOS --}}
        <section id="trabalhos" class="sec-carvao">
            <div class="wrap">
                <div class="reveal">
                    <span class="eyebrow">{!! PageContent::def('trabalhos', 'eyebrow') !!}</span>
                    <hr class="regua">
                    <h2>{!! PageContent::def('trabalhos', 'titulo') !!}</h2>
                </div>
                <div class="galeria">
                    <figure class="reveal"><img src="{{ $cortes[0] }}" alt="Trabalho da casa — Frioli Hair" loading="lazy"></figure>
                    <figure class="reveal"><img src="{{ $cortes[1] }}" alt="Trabalho da casa — Frioli Hair" loading="lazy"></figure>
                    <figure class="reveal"><img src="{{ $cortes[2] }}" alt="Trabalho da casa — Frioli Hair" loading="lazy"></figure>
                    <figure class="reveal"><img src="{{ $cortes[3] }}" alt="Trabalho da casa — Frioli Hair" loading="lazy"></figure>
                </div>
            </div>
        </section>

        {{-- AVALIAÇÕES --}}
        <section id="avaliacoes" class="avaliacoes">
            <div class="wrap">
                <div class="reveal" style="text-align:center">
                    <span class="eyebrow">{!! PageContent::def('avaliacoes', 'eyebrow') !!}</span>
                    <hr class="regua" style="margin-left:auto;margin-right:auto">
                    <h2>{!! PageContent::def('avaliacoes', 'titulo') !!}</h2>
                </div>

                <div class="ma-head reveal">
                    <div class="ma-nota">
                        <b>0,0</b>
                        <span class="ma-stars">★★★★★</span>
                    </div>
                    <p class="ma-rotulo">{!! PageContent::def('avaliacoes', 'rotulo') !!}</p>
                </div>

                <div class="ma-vp reveal">
                    <div class="ma-track">
                        @foreach([$reviews, $reviews] as $grupo)
                            @foreach($grupo as $r)
                                <article class="ma-card sx-glass">
                                    <span class="ma-stars">★★★★★</span>
                                    <p>{{ $r[0] }}</p>
                                    <footer>{{ $r[1] }}</footer>
                                </article>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                <p class="ma-fonte reveal">
                    <a href="https://maps.google.com/?cid=13020782659599815725" target="_blank" rel="noopener">Ver todas as avaliações no Google</a>
                </p>
            </div>
        </section>

        {{-- LOCAL --}}
        <section id="local" class="sec-creme">
            <a id="endereco"></a>
            <div class="wrap">
                <div class="local">
                    <div class="mapa reveal">
                        <iframe src="{{ $mapaSrc }}" title="Mapa da Barbearia Frioli Hair" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <div class="reveal">
                        <span class="eyebrow">{!! PageContent::def('local', 'eyebrow') !!}</span>
                        <hr class="regua">
                        <h2 style="margin-bottom:28px">{!! PageContent::def('local', 'titulo') !!}</h2>
                        <div class="linha-info"><span class="rot">Endereço</span><span class="v">{{ nl2br(e($endRua)) }}</span></div>
                        <div class="linha-info"><span class="rot">WhatsApp</span><span class="v"><a href="{{ $waLink }}" target="_blank" rel="noopener">{{ $wa }}</a></span></div>
                        <div class="linha-info"><span class="rot">Instagram</span><span class="v"><a href="{{ $igUrl }}" target="_blank" rel="noopener">{{ $ig }}</a></span></div>
                        <div class="linha-info"><span class="rot">Horário</span><span class="v">{!! PageContent::def('local', 'horario') !!}</span></div>
                        <a class="btn-ouro" href="{{ $rotaAgendar }}">Agendar meu horário</a>
                    </div>
                </div>
            </div>
        </section>

        {{-- CTA FINAL --}}
        <section class="cta-final">
            <div class="wrap">
                <h2 class="reveal">{!! PageContent::def('cta', 'titulo') !!}</h2>
                <p class="reveal">{!! PageContent::def('cta', 'texto') !!}</p>
                <a class="btn-escuro reveal" href="{{ $rotaAgendar }}">{{ PageContent::def('cta', 'btn') }}</a>
            </div>
        </section>

        {{-- RODAPÉ DA HOME --}}
        <footer class="fh-footer">
            <div class="wrap">
                <div class="marca"><img src="{{ $logo }}" alt="Barbearia Frioli Hair"></div>
                <div class="fmeta">
                    {{ $endRua }}<br>
                    <a href="{{ $waLink }}">{{ $wa }}</a> · <a href="{{ $igUrl }}" target="_blank" rel="noopener">{{ $ig }}</a><br>
                    Barbearia desde 2015
                </div>
            </div>
        </footer>

        <form id="fh-logout" action="{{ url('/logout') }}" method="POST" style="display:none">@csrf</form>

        {{-- CTA FLUTUANTE — canto inferior direito; some enquanto o hero está em cena --}}
        <a class="fab-agendar" href="{{ $rotaAgendar }}">
            <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="6" cy="6" r="3"/><path d="M8.12 8.12 12 12"/><path d="M20 4 8.12 15.88"/><circle cx="6" cy="18" r="3"/><path d="M14.8 14.8 20 20"/></svg>
            Agende agora
        </a>
    </div>
@endsection
@section("scriptEnd")
<script>
(function () {
    var fh = document.querySelector('.fh');
    if (!fh) return;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fine = window.matchMedia('(pointer: fine)').matches;

    requestAnimationFrame(function () { fh.classList.add('aberto'); });

    var nav = document.querySelector('.fh-nav');
    var fab = document.querySelector('.fab-agendar');
    var hero = document.querySelector('.fh .hero');
    function onScroll() {
        var y = window.scrollY || window.pageYOffset;
        if (nav) nav.classList.toggle('solida', y > 80);
        // CTA flutuante: some enquanto o hero domina a tela (o CTA do hero já cobre)
        if (fab) fab.classList.toggle('fab-fora', hero ? y < hero.offsetHeight * 0.75 : y < 400);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (e.isIntersecting) { e.target.classList.add('visivel'); io.unobserve(e.target); }
        });
    }, { threshold: 0.14 });
    document.querySelectorAll('.fh .reveal').forEach(function (el) { io.observe(el); });

    var varre = document.querySelector('#varredura-luz');
    var dentes = Array.prototype.slice.call(document.querySelectorAll('.dente'));
    var guias = Array.prototype.slice.call(document.querySelectorAll('.fh .guia'));
    var guiaX = [100, 300, 500, 700, 900];
    if (reduce) {
        guias.forEach(function (g) { g.classList.add('acesa'); });
    } else if (varre) {
        var x = -150;
        (function tick() {
            x += 7;
            if (x > 1150) { x = -150; guias.forEach(function (g) { g.classList.remove('acesa'); }); }
            varre.setAttribute('x', x);
            dentes.forEach(function (d) {
                var dx = parseFloat(d.getAttribute('data-x'));
                d.setAttribute('opacity', Math.abs(dx - x) < 60 ? '0.9' : '0');
            });
            guias.forEach(function (g, i) { if (x > guiaX[i]) g.classList.add('acesa'); });
            requestAnimationFrame(tick);
        })();
    }

    var helice = document.querySelector('#helice');
    if (!reduce && helice) {
        var y = 0;
        setInterval(function () { y = (y + 2) % 60; helice.setAttribute('transform', 'translate(0,' + (-y) + ')'); }, 28);
    }
    var etapas = Array.prototype.slice.call(document.querySelectorAll('.fh .etapa'));
    if (!reduce && etapas.length) {
        var i = 0;
        etapas[0].classList.add('ativa');
        setInterval(function () {
            etapas.forEach(function (e) { e.classList.remove('ativa'); });
            i = (i + 1) % etapas.length;
            etapas[i].classList.add('ativa');
        }, 2200);
    }

    if (!reduce && fine) {
        document.querySelectorAll('.fh .sx-glass').forEach(function (g) {
            g.addEventListener('mousemove', function (e) {
                var r = g.getBoundingClientRect();
                g.style.setProperty('--gx', ((e.clientX - r.left) / r.width * 100) + '%');
                g.style.setProperty('--gy', ((e.clientY - r.top) / r.height * 100) + '%');
                g.style.setProperty('--glow', '1');
            });
            g.addEventListener('mouseleave', function () { g.style.setProperty('--glow', '.4'); });
        });
    }

    var notaEl = document.querySelector('.fh .ma-nota b');
    if (notaEl) {
        if (reduce) {
            notaEl.textContent = '5,0';
        } else {
            var ioN = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) return;
                    var v = 0;
                    var t = setInterval(function () {
                        v += 0.2;
                        if (v >= 5) { v = 5; clearInterval(t); }
                        notaEl.textContent = (Math.round(v * 10) / 10).toFixed(1).replace('.', ',');
                    }, 40);
                    ioN.disconnect();
                });
            }, { threshold: 0.5 });
            ioN.observe(notaEl);
        }
    }
})();
</script>
@endsection
