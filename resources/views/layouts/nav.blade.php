<style>
    .nav {
        background-color: var(--branco);
    }
</style>

<nav class="nav shadow-sm d-none d-md-block">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-6 py-3 d-flex align-items-center" onclick="window.location.href='/'" style="cursor: pointer">
                <img src="{{ \App\Models\PageContent::image('branding', 'logo', Storage::url('logo/logo-navbar.jpeg')) }}" alt="Barbearia Frioli" style="height: 50px; width: auto; border-radius: 10px;">
            </div>
            <div class="col-6 text-end form-text text-secondary pe-md-5">
                <a href="{{url('/')}}/#sobre" class="menu-item-sm">A barbearia</a>
                <a href="{{url('/')}}/#ritual" class="menu-item-sm">Como funciona</a>
                <a href="{{url('/')}}/#servicos" class="menu-item-sm">Serviços</a>
                <a href="{{url('/')}}/#barbeiros" class="menu-item-sm">Barbeiros</a>
                <a href="{{url('/')}}/#avaliacoes" class="menu-item-sm">Avaliações</a>
                <a href="{{url('/')}}/#local" class="menu-item-sm">Onde ficamos</a>
                {{-- Conta (Painel/Admin/Financeiro/Sair) fica no menu lateral (hambúrguer),
                     visível em qualquer tamanho — evita empilhar a navbar em 2 linhas. --}}
                @if(!isset(Auth::user()->id))
                    <a class="menu-item-sm" href="{{url('/')}}/login">Login</a>
                @endif
            </div>
        </div>
    </div>
</nav>

<div class="side-menu">
    @php $au = auth()->user(); $ra = !$au ? route('agendar.entrar') : ($au->whatsappVerificado() ? route('agendar.index') : route('verificar.whatsapp')); @endphp
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='{{url('/')}}/#sobre'},500)">
        A barbearia
    </div>
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='{{url('/')}}/#ritual'},500)">
        Como funciona
    </div>
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='{{url('/')}}/#servicos'},500)">
        Serviços
    </div>
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='{{url('/')}}/#barbeiros'},500)">
        Barbeiros
    </div>
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='{{url('/')}}/#avaliacoes'},500)">
        Avaliações
    </div>
    <div class="menu-item" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='{{url('/')}}/#local'},500)">
        Onde ficamos
    </div>
    {{-- "Agendar horário" é do cliente — staff (adm/func) agenda pelos clientes no painel. --}}
    @if(!isset(Auth::user()->id) || (!Auth::user()->adm && !Auth::user()->func))
    <div class="menu-item" style="color: var(--marrom); font-weight: 600" onclick="$('.menu-icon').click() && setTimeout(()=>{window.location.href='{{ $ra }}'},500)">
        Agendar horário
    </div>
    @endif

    {{-- Rotas autenticadas --}}
    @if(isset(Auth::user()->id))
        <div class="menu-item" onclick="window.location.href='dashboard'">
            Painel
        </div>
        @if(Auth::user()->adm == 1)
        <div class="menu-item" onclick="window.location.href='{{ url('/admin') }}'">
            Painel Admin
        </div>
        <div class="menu-item" onclick="window.location.href='{{ route('financeiro.index') }}'">
            Financeiro
        </div>
        @endif
        {{-- LOGOUT --}}
        <div class="menu-item menu-bottom" onclick="$('#logout-form').submit()">
            SAIR
            <form id="logout-form" action="{{url('/')}}/logout" method="POST">
                @csrf
                @method('POST')
            </form>
        </div>
    @else
        {{-- LOGOUT --}}
        <div class="menu-item menu-bottom" onclick="window.location.href='{{url('/')}}/login'">
            LOGIN
        </div>
    @endif
</div>

{{-- Backdrop --}}
<div class="backdrop-menu position-fixed d-none vw-100 vh-100"></div>
{{-- Hambúrguer sempre visível (desktop incluso): abre o menu lateral com a conta
     (Painel/Admin/Financeiro/Sair) e as seções do site. --}}
<div class="d-block">
    <svg class="menu-icon" id="menu-bars" height="2rem" viewBox="0 -960 960 960" width="2rem" fill="#c9a36f"><path d="M120-240v-80h720v80H120Zm0-200v-80h720v80H120Zm0-200v-80h720v80H120Z"/></svg>
    <svg class="menu-icon d-none" id="menu-times" height="2rem" viewBox="0 -960 960 960" width="2rem" fill="#c9a36f"><path d="m256-200-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 224 224-56 56-224-224-224 224Z"/></svg>
</div>

<style>
    .menu-icon {
        cursor: pointer;
        z-index: 9999;
        position: fixed;
        top: 5px;
        right: 5px;
    }
    .menu-bottom {
        position: absolute ;
        bottom: 10px;
        font-size: 10px;
    }
    .side-menu {
        position: fixed;
        z-index: 99;
        top: 0;
        right: -310px;
        width: 300px;
        height: 100%;
        background-color: var(--branco);
        padding: 10px;
        padding-top: 40px;
        border-right: 1px solid #000000;
    }
    .menu-item {
        cursor: pointer;
        z-index: 999;
        padding: 10px;
        width: 100%;
    }
    .menu-item-sm {
        cursor: pointer;
        z-index: 999;
        padding: 10px;
        width: 100%;
        text-decoration: none;
        color: #e8ddc6;
    }
    .menu-item-sm:hover {
        color: var(--marrom);
    }
    .menu-item:hover {
        background-color: var(--cinza);
    }
    .backdrop-menu {
        position: fixed;
        z-index: 9;
        height: 100vh;
        width: 100vw;
        top: 0;
        right: 0;
        backdrop-filter: blur(5px);
    }
</style>

<script>
    $('.menu-icon').on('click', function(){
        if($('.side-menu').css('right') < '0') {
            $('#menu-bars').addClass('d-none');
            $('#menu-times').removeClass('d-none');
            $('.backdrop-menu').removeClass('d-none');
            $('#menu-times').animate({
                right: "260px",
            })
            $('.side-menu').animate({
                right: "0px",
            })
        }else{
            $('#menu-bars').removeClass('d-none');
            $('#menu-times').addClass('d-none');
            $('.backdrop-menu').addClass('d-none');
            $('#menu-times').animate({
                right: "0px",
            })
            $('.side-menu').animate({
                right: "-310px",
            })
        }
    });
    $('.main').on('click', function(){
        $('#menu-bars').removeClass('d-none');
        $('#menu-times').addClass('d-none');
        $('.side-menu').animate({
            right: "-310px",
        })
    });
</script>