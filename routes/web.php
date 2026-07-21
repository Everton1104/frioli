<?php

use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Agenda\AgendaPublicaController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Agenda\ServicoController;
use App\Http\Controllers\CreditoServicoController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PublicRegistrationController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\OrdemPagamentoController;
use App\Http\Controllers\InfinitePayWebhookController;
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Models\AgendamentoModel;
use App\Models\Aviso;
use App\Models\OrdemPagamento;
use App\Models\ServicosModel;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

// ── Agendamento público (booking self-service) ───────────────────────────────
// Cadastro/entrada por OTP: visitante vira User verificado e segue para /agendar.
Route::middleware('guest')->prefix('agendar')->name('agendar.')->group(function () {
    Route::get('/entrar',                 [PublicRegistrationController::class, 'entrar'])->name('entrar');
    Route::post('/entrar',                [PublicRegistrationController::class, 'enviarCodigo'])->name('entrar.codigo');
    Route::get('/verificar',              [PublicRegistrationController::class, 'verificar'])->name('verificar');
    Route::post('/verificar',             [PublicRegistrationController::class, 'confirmar'])->name('verificar.confirmar');
    Route::post('/verificar/reenviar',    [PublicRegistrationController::class, 'reenviar'])->name('verificar.reenviar');
});

// O booking em si (cliente autenticado e com WhatsApp verificado).
// Só agendamento avulso: planos mensais são criados pelo staff (OrdemPagamentoController).
Route::middleware(['auth', 'whatsapp.verified'])->prefix('agendar')->name('agendar.')->group(function () {
    Route::get('/',            [AgendaPublicaController::class, 'index'])->name('index');
    Route::post('/reservar',   [AgendaPublicaController::class, 'reservar'])->name('reservar');
});

Route::get('/api/mensal/calcular', [AgendaPublicaController::class, 'mensalCalcular']);

Route::get('/dashboard', function () {
    $user     = auth()->user();
    $users    = User::where('excluido', '0')->orderBy('adm', 'desc')->orderBy('func', 'desc')->paginate(10);
    $clientes = User::where([['excluido', '0'], ['func', '0'], ['adm', '0']])->get();
    $servicos = ServicosModel::where('excluido', '0')->get();
    $mesAtual = now()->locale('pt_BR')->translatedFormat('F Y');

    // Limita a um período visível (mês anterior em diante) em vez de carregar TODO o
    // histórico a cada acesso — a consulta crescia sem limite. Para ver registros
    // antigos use a busca (agenda.search), que filtra por nome do cliente.
    $inicioJanela = now()->startOfMonth()->subMonth();

    $consultasQuery = AgendamentoModel::with([
        'user',
        'funcionario',
        'servico',
        'lembretes',
        'creditoServico' => fn($q) => $q->with(['servico', 'agendamentos:id,credito_servico_id'])->withCount('agendamentos'),
    ])->where('data_inicio', '>=', $inicioJanela)->orderBy('data_inicio');

    // Escopo por papel: cliente → seus agendamentos; barbeiro (func) → só a própria
    // agenda; admin → todos (ou filtra por um barbeiro via ?barbeiro=ID).
    $barbeiroSelecionado = null;
    if (!$user->adm && !$user->func) {
        $consultasQuery->where('user_id', $user->id);
    } elseif ($user->func) {
        $consultasQuery->where('funcionario_id', $user->id);
    } elseif (request('barbeiro')) {
        $barbeiroSelecionado = (int) request('barbeiro');
        $consultasQuery->where('funcionario_id', $barbeiroSelecionado);
    }

    // Lista de barbeiros para o seletor/filtro (admin) e para o agendamento pelo staff.
    $barbeiros = User::barbeiros()->get();

    $hoje      = now()->toDateString();
    $consultas = $consultasQuery->get()->groupBy([
        fn($item) => \Carbon\Carbon::parse($item->data_inicio)->locale('pt_BR')->translatedFormat('F Y'),
        fn($item) => \Carbon\Carbon::parse($item->data_inicio)->format('Y-m-d'),
    ]);

    $avisos = ($user->adm || $user->func)
        ? Aviso::with(['user', 'servico'])->whereNull('dispensado_at')->latest()->get()
        : collect();

    // Ordens de pagamento: visíveis SOMENTE para administradores; paciente só as suas.
    $ordensPagamento = $user->adm
        ? OrdemPagamento::with(['user', 'criador'])->latest()->limit(50)->get()
        : collect();
    $minhasOrdens = (!$user->adm && !$user->func)
        ? OrdemPagamento::where('user_id', $user->id)->latest()->get()
        : collect();

    // Booking público: agendamentos pagos aguardando confirmação do staff.
    // Barbeiro vê só os seus; admin pode estar filtrando por um barbeiro.
    $pendentes = ($user->adm || $user->func)
        ? AgendamentoModel::with(['user', 'servico'])
            ->where('status', AgendamentoModel::STATUS_PAGO_AGUARDANDO)
            ->when($user->func, fn($q) => $q->where('funcionario_id', $user->id))
            ->when($barbeiroSelecionado, fn($q) => $q->where('funcionario_id', $barbeiroSelecionado))
            ->orderBy('data_inicio')->get()
        : collect();

    // Clientes penalizados (no-show) — staff (adm/func) pode remover a penalidade.
    $penalizados = ($user->adm || $user->func)
        ? User::where('penalizado', 1)->where('excluido', 0)->orderByDesc('penalizado_em')->get()
        : collect();

    // Pacotes do cliente (tela "Meus pacotes" — validade/Negociar).
    $meusCreditos = (!$user->adm && !$user->func)
        ? \App\Models\CreditoServico::with('servico')->withCount('agendamentos')
            ->where('user_id', $user->id)->orderByDesc('id')->get()
        : collect();

    $whatsappAdmin = \App\Models\PageContent::get('contato', 'whatsapp_numero', '5511988245815');

    // Planos mensais (Fase C): staff vê todos; cliente vê os seus.
    $planos = ($user->adm || $user->func)
        ? \App\Models\PlanoMensal::with(['user:id,name', 'servico:id,descricao', 'funcionario:id,name'])->latest()->limit(50)->get()
        : collect();
    $meusPlanos = (!$user->adm && !$user->func)
        ? \App\Models\PlanoMensal::with(['servico:id,descricao', 'funcionario:id,name'])->where('user_id', $user->id)->latest()->get()
        : collect();

    return view('dashboard', compact('users', 'clientes', 'servicos', 'consultas', 'mesAtual', 'hoje', 'avisos', 'ordensPagamento', 'minhasOrdens', 'pendentes', 'barbeiros', 'barbeiroSelecionado', 'penalizados', 'meusCreditos', 'whatsappAdmin', 'planos', 'meusPlanos'));
})->middleware(['auth', 'verified', 'whatsapp.verified'])->name('dashboard');

Route::get('/api/horarios/{data}', [AgendaController::class, 'horarios']);
Route::get('/api/dias-disponiveis/{ano}/{mes}', [AgendaController::class, 'diasDisponiveis']);
Route::get('/api/disponibilidade/{data}', [AgendaController::class, 'getSlotsDia'])->middleware('auth');
Route::post('/api/disponibilidade/{data}', [AgendaController::class, 'salvarSlots'])->middleware('auth');
Route::post('/api/agenda/horario-comercial', [AgendaController::class, 'salvarHorarioComercial'])->middleware('auth');
Route::post('/api/disponibilidade/semana/{domingo}', [AgendaController::class, 'salvarSemana'])->middleware('auth');

Route::post('add-usuario', [RegisteredUserController::class, 'store'])->middleware(['auth', 'verified'])->name('add-usuario');
Route::post('delete-usuario', [RegisteredUserController::class, 'delete'])->middleware('auth')->name('delete-usuario');
Route::post('editar-usuario', [RegisteredUserController::class, 'editar'])->middleware('auth')->name('editar-usuario');
Route::get('usuarios-search', [RegisteredUserController::class, 'search'])->middleware('auth')->name('usuarios.search');

// ── Créditos/serviços contratados por cliente (somente staff) ─────────────────
Route::middleware('auth')->group(function () {
    Route::get('/api/clientes/{userId}/creditos',              [CreditoServicoController::class, 'index'])->name('creditos.index');
    Route::post('/clientes/{userId}/creditos',                 [CreditoServicoController::class, 'store'])->name('creditos.store');
    Route::delete('/clientes/{userId}/creditos/{creditoId}',   [CreditoServicoController::class, 'destroy'])->name('creditos.destroy');
});
Route::resource('agenda', AgendaController::class)->middleware('auth');
Route::post('agenda/{id}/confirmar', [AgendaController::class, 'confirmar'])->middleware('auth')->name('agenda.confirmar');
Route::post('agenda/{id}/recusar',   [AgendaController::class, 'recusar'])->middleware('auth')->name('agenda.recusar');
Route::post('agenda/{id}/reenviar-lembrete', [AgendaController::class, 'reenviarLembrete'])->middleware('auth')->name('agenda.reenviar-lembrete');
Route::post('agenda/{id}/comparecimento', [AgendaController::class, 'comparecimento'])->middleware('auth')->name('agenda.comparecimento');
Route::post('usuario/{id}/remover-penalidade', [AgendaController::class, 'removerPenalidade'])->middleware('auth')->name('usuario.remover-penalidade');
Route::post('aviso/{id}/dispensar', [AgendaController::class, 'dispensarAviso'])->middleware('auth')->name('aviso.dispensar');
Route::get('avisos-parcial', [AgendaController::class, 'avisosParcial'])->middleware('auth')->name('avisos.parcial');
Route::get('agenda-search', [AgendaController::class, 'search'])->middleware('auth')->name('agenda.search');
Route::resource('servico', ServicoController::class)->middleware('auth');
Route::post('delete-servico', [ServicoController::class, 'delete'])->middleware('auth')->name('delete-servico');
Route::post('editar-servico', [ServicoController::class, 'editar'])->middleware('auth')->name('editar-servico');


// ── WhatsApp webhook (público) ────────────────────────────────────────────────
Route::get('/whatsapp/webhook',  [WhatsappController::class, 'verifyToken']);
Route::post('/whatsapp/webhook', [WhatsappController::class, 'getMsgs']);

// Callback do gateway central (evtu.com.br): cliques de botão roteados a partir
// do webhook único compartilhado entre sistemas. Assinado com WHATSAPP_GATEWAY_KEY.
Route::post('/whatsapp/inbound', [WhatsappController::class, 'inbound']);

// ── WhatsApp verificação de número ────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/cadastrar-whatsapp', function () {
        if (auth()->user()->whatsapp) {
            return redirect()->route('verificar.whatsapp');
        }
        return view('auth.cadastrar-whatsapp');
    })->name('cadastrar.whatsapp');

    Route::post('/cadastrar-whatsapp', function (\Illuminate\Http\Request $req) {
        $req->validate(['whatsapp' => 'required|string|max:20']);
        $numero = preg_replace('/\D/', '', $req->whatsapp);
        if (strlen($numero) < 10) {
            return back()->withErrors(['whatsapp' => 'Número inválido.'])->withInput();
        }
        $user = auth()->user();
        $user->whatsapp              = '55' . $numero;
        $user->whatsapp_verified_at  = null;
        $user->save();
        WhatsappController::enviarCodigoVerificacao($user);
        return redirect()->route('verificar.whatsapp')->with('status', 'Código enviado!');
    })->name('cadastrar.whatsapp.salvar');

    Route::get('/verificar-whatsapp', function () {
        $user = auth()->user();
        if ($user->whatsappVerificado()) {
            return redirect()->route('dashboard');
        }
        $codigoEnviado = !is_null($user->whatsapp_code_expires_at) && now()->lt($user->whatsapp_code_expires_at);
        $aguardar = $codigoEnviado
            ? max(0, (int) $user->whatsapp_code_expires_at->diffInSeconds(now()) - 540)
            : 0;
        return view('auth.verificar-whatsapp', compact('aguardar', 'codigoEnviado'));
    })->name('verificar.whatsapp');

    Route::post('/verificar-whatsapp', function (\Illuminate\Http\Request $req) {
        $req->validate(['codigo' => 'required|string|size:6']);
        $user = auth()->user();
        if ($user->whatsapp_code !== $req->codigo || now()->gt($user->whatsapp_code_expires_at)) {
            return back()->withErrors(['codigo' => 'Código inválido ou expirado.']);
        }
        $user->whatsapp_verified_at     = now();
        $user->whatsapp_code            = null;
        $user->whatsapp_code_expires_at = null;
        $user->save();
        return redirect()->route('dashboard')->with('msg', 'WhatsApp verificado com sucesso!');
    })->name('verificar.whatsapp.confirmar');

    Route::post('/verificar-whatsapp/reenviar', function () {
        $user = auth()->user();
        if ($user->whatsappVerificado()) {
            return redirect()->route('dashboard');
        }
        $aguardar = ($user->whatsapp_code_expires_at && now()->lt($user->whatsapp_code_expires_at))
            ? max(0, (int) $user->whatsapp_code_expires_at->diffInSeconds(now()) - 540)
            : 0;
        if ($aguardar > 0) {
            return redirect()->route('verificar.whatsapp')
                ->with('error', "Aguarde {$aguardar}s antes de reenviar.");
        }
        WhatsappController::enviarCodigoVerificacao($user);
        return redirect()->route('verificar.whatsapp')->with('status', 'Código enviado!');
    })->name('verificar.whatsapp.reenviar');
});

// ── Ordens de pagamento (InfinitePay — Link de Pagamento / redirect) ─────────
Route::middleware('auth')->group(function () {
    // Staff (adm/func) — criar/cancelar/excluir ordens (autorização no controller).
    Route::post('/ordens-pagamento',               [OrdemPagamentoController::class, 'store'])->name('ordens.store');
    Route::post('/planos-mensais',                 [OrdemPagamentoController::class, 'mensalStore'])->name('planos.store');
    Route::post('/ordens-pagamento/{id}/cancelar', [OrdemPagamentoController::class, 'cancelar'])->name('ordens.cancelar');
    Route::delete('/ordens-pagamento/{id}',        [OrdemPagamentoController::class, 'destroy'])->name('ordens.destroy');
    // Paciente — tela de checkout (GET) e criação do link de pagamento (POST, throttle).
    Route::get ('/pagamentos/{ordem}/pagar', [OrdemPagamentoController::class, 'pagar'])->name('pagamentos.pagar');
    Route::post('/pagamentos/{ordem}/link',  [OrdemPagamentoController::class, 'link'])->middleware('throttle:30,1')->name('pagamentos.link');
});

// Retorno do checkout e polling de status (sem auth) — validados pelo `ref`
// (external_reference) no controller. Sobrevivem à expiração de sessão e aos
// query params extras que a InfinitePay adiciona ao redirecionar.
Route::get('/pagamentos/{ordem}/retorno', [OrdemPagamentoController::class, 'retorno'])->name('pagamentos.retorno');
Route::get('/pagamentos/{ordem}/status',  [OrdemPagamentoController::class, 'status'])->name('pagamentos.status');

// Webhook público da InfinitePay (token no path; fora do CSRF — ver bootstrap/app.php).
Route::post('/infinitepay/webhook/{token}', [InfinitePayWebhookController::class, 'handle'])->name('infinitepay.webhook');

// Webhook público do MP (mantido inerte — ordens antigas ainda podem notificar).
Route::post('/mercadopago/webhook', [MercadoPagoWebhookController::class, 'handle'])->name('mercadopago.webhook');

// ── Painel admin: edição de conteúdo + galeria de fotos (somente adm) ──────────
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/',                      [AdminController::class, 'index'])->name('index');
    Route::get('/fotos',                 [AdminController::class, 'fotos'])->name('fotos');
    Route::post('/fotos/upload',         [AdminController::class, 'fotosUpload'])->name('fotos.upload');
    Route::patch('/fotos/{foto}',        [AdminController::class, 'fotosUpdate'])->name('fotos.update');
    Route::delete('/fotos/{foto}',       [AdminController::class, 'fotosDelete'])->name('fotos.delete');
    Route::post('/fotos/reorder',        [AdminController::class, 'fotosReorder'])->name('fotos.reorder');
    // Genéricas de conteúdo (depois das rotas nomeadas p/ não capturar "fotos"):
    Route::get('/{section}/{key}/edit',  [AdminController::class, 'edit'])->name('edit');
    Route::put('/{section}/{key}',       [AdminController::class, 'update'])->name('update');
});

require __DIR__.'/auth.php';
