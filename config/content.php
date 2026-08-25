<?php

/**
 * Fonte única (single source of truth) dos textos padrão do site editorial.
 * Cada item: [section => [key => ['label' => string, 'value' => string]]].
 *
 * - A migration `seed_editorial_content` cria as linhas em `page_contents` a
 *   partir deste arquivo (updateOrCreate, migrate-once) → ficam editáveis no
 *   painel /admin.
 * - Nas views: `PageContent::def('section','key')` lê o valor do banco (se a
 *   linha existir) ou cai neste default (se ainda não tiver sido criada).
 *   Valores podem conter HTML básico — renderize com {!! ... !!}.
 */
return [

    // ── HOME / HERO ──────────────────────────────────────────────
    'hero' => [
        'headline' => ['label' => 'Hero — título (H1)', 'value' => 'Você traz a referência.<br>A gente entrega<br>no <em>seu cabelo</em>.'],
        'lede'     => ['label' => 'Hero — subtítulo', 'value' => 'Onze anos cortando em Jundiaí. Aqui o corte sai do jeito que combina com você, do degradê à barba.'],
    ],

    // ── RÉGUA / STATS ────────────────────────────────────────────
    'prova' => [
        'eyebrow' => ['label' => 'Régua — olho (label pequeno)', 'value' => 'A casa em número'],
        'titulo'  => ['label' => 'Régua — título', 'value' => 'Onze anos na mesma régua'],
        'lede'    => ['label' => 'Régua — subtítulo', 'value' => 'Barbearia que abriu numa garagem em 2015 e nunca terceirizou o acabamento.'],
        'n1'  => ['label' => 'Régua — número 1', 'value' => '2015'],
        'r1'  => ['label' => 'Régua — rótulo 1', 'value' => 'o começo'],
        'v1'  => ['label' => 'Régua — valor 1', 'value' => 'numa garagem'],
        'n2'  => ['label' => 'Régua — número 2', 'value' => '11'],
        'r2'  => ['label' => 'Régua — rótulo 2', 'value' => 'anos'],
        'v2'  => ['label' => 'Régua — valor 2', 'value' => 'de Jundiaí'],
        'n3'  => ['label' => 'Régua — número 3', 'value' => '2'],
        'r3'  => ['label' => 'Régua — rótulo 3', 'value' => 'cadeiras'],
        'v3'  => ['label' => 'Régua — valor 3', 'value' => 'Frioli e Tiago'],
        'n4'  => ['label' => 'Régua — número 4', 'value' => '300+'],
        'r4'  => ['label' => 'Régua — rótulo 4', 'value' => 'atendimentos/mês'],
        'v4'  => ['label' => 'Régua — valor 4', 'value' => 'na régua da casa'],
        'n5'  => ['label' => 'Régua — número 5', 'value' => '5,0'],
        'r5'  => ['label' => 'Régua — rótulo 5', 'value' => 'no Google'],
        'v5'  => ['label' => 'Régua — valor 5', 'value' => 'em 72 avaliações'],
    ],

    // ── SOBRE ────────────────────────────────────────────────────
    'sobre' => [
        'eyebrow' => ['label' => 'Sobre — olho', 'value' => 'A barbearia'],
        'titulo'  => ['label' => 'Sobre — título', 'value' => 'Começou numa garagem.<br>Virou ponto de Jundiaí.'],
        'texto'   => ['label' => 'Sobre — texto (HTML)', 'value' => '<p>O Frioli abriu em 2015, numa garagem, cortando cabelo de quem confiava no trabalho dele. Onze anos depois é uma barbearia de verdade, com duas cadeiras e um jeito próprio de atender.</p><p>Aqui o corte não sai no automático. Você chega com uma ideia, a gente lê o que o teu cabelo permite e faz o corte certo pra ti. Cliente que entra volta, e a maioria vira de casa.</p>'],
    ],

    // ── O ESPAÇO ─────────────────────────────────────────────────
    'espaco' => [
        'eyebrow' => ['label' => 'Espaço — olho', 'value' => 'O espaço'],
        'titulo'  => ['label' => 'Espaço — título', 'value' => 'Onde o corte acontece'],
        'lede'    => ['label' => 'Espaço — subtítulo', 'value' => 'Três cadeiras de couro, piso claro e bancada de mármore. Rua do Retiro, 329, no Retiro.'],
    ],

    // ── COMO FUNCIONA / RITUAL ───────────────────────────────────
    'ritual' => [
        'eyebrow' => ['label' => 'Ritual — olho', 'value' => 'Como funciona'],
        'titulo'  => ['label' => 'Ritual — título', 'value' => 'O corte sai da conversa'],
        'lede'    => ['label' => 'Ritual — subtítulo', 'value' => 'O diferencial da casa é técnico e consultivo. Você mostra o que quer, a gente adapta pro que funciona no seu cabelo e no seu rosto.'],
        'e1_t' => ['label' => 'Ritual — etapa 1 (título)', 'value' => 'Você mostra'],
        'e1_x' => ['label' => 'Ritual — etapa 1 (texto)', 'value' => 'Chega com a foto, o print, o corte que viu e curtiu. A conversa começa por aí.'],
        'e2_t' => ['label' => 'Ritual — etapa 2 (título)', 'value' => 'Nós lemos o seu corte'],
        'e2_x' => ['label' => 'Ritual — etapa 2 (texto)', 'value' => 'O barbeiro avalia o que o seu cabelo permite e diz o que dá pra fazer de verdade. Sem prometer o impossível.'],
        'e3_t' => ['label' => 'Ritual — etapa 3 (título)', 'value' => 'Corte e barba'],
        'e3_x' => ['label' => 'Ritual — etapa 3 (texto)', 'value' => 'Máquina, tesoura, degradê, navalha. O acabamento é onde a casa não abre mão.'],
        'e4_t' => ['label' => 'Ritual — etapa 4 (título)', 'value' => 'Você sai pronto'],
        'e4_x' => ['label' => 'Ritual — etapa 4 (texto)', 'value' => 'Com o corte que combina com você, feito pra durar o mês e ficar bom desde o primeiro dia.'],
    ],

    // ── SERVIÇOS ─────────────────────────────────────────────────
    'servicos' => [
        'eyebrow' => ['label' => 'Serviços — olho', 'value' => 'O que fazemos'],
        'titulo'  => ['label' => 'Serviços — título', 'value' => 'Serviços'],
        'nota'    => ['label' => 'Serviços — rodapé', 'value' => 'Valores dos avulsos confirmados no agendamento. O pacote mensal é o que a maioria dos clientes de casa contrata.'],
        's1_nome'  => ['label' => 'Serviço 1 — nome',  'value' => 'Corte'],
        's1_desc'  => ['label' => 'Serviço 1 — descrição', 'value' => 'Máquina, tesoura, degradê, clássico ou moderno'],
        's1_preco' => ['label' => 'Serviço 1 — preço', 'value' => 'R$ a definir'],
        's2_nome'  => ['label' => 'Serviço 2 — nome',  'value' => 'Corte + Barba'],
        's2_desc'  => ['label' => 'Serviço 2 — descrição', 'value' => 'O combo mais pedido da casa'],
        's2_preco' => ['label' => 'Serviço 2 — preço (destaque)', 'value' => 'R$80'],
        's3_nome'  => ['label' => 'Serviço 3 — nome',  'value' => 'Barba'],
        's3_desc'  => ['label' => 'Serviço 3 — descrição', 'value' => 'Desenho, toalha quente e acabamento na navalha'],
        's3_preco' => ['label' => 'Serviço 3 — preço', 'value' => 'R$ a definir'],
        's4_nome'  => ['label' => 'Serviço 4 — nome',  'value' => 'Sobrancelha'],
        's4_desc'  => ['label' => 'Serviço 4 — descrição', 'value' => 'Alinhamento no capricho'],
        's4_preco' => ['label' => 'Serviço 4 — preço', 'value' => 'R$ a definir'],
        's5_nome'  => ['label' => 'Serviço 5 — nome',  'value' => 'Corte infantil'],
        's5_desc'  => ['label' => 'Serviço 5 — descrição', 'value' => 'Estilo não tem idade, os pequenos também'],
        's5_preco' => ['label' => 'Serviço 5 — preço', 'value' => 'R$ a definir'],
        's6_nome'  => ['label' => 'Serviço 6 — nome',  'value' => 'Barboterapia'],
        's6_desc'  => ['label' => 'Serviço 6 — descrição', 'value' => 'Cuidado completo pra barba e pele'],
        's6_preco' => ['label' => 'Serviço 6 — preço', 'value' => 'R$ a definir'],
        'pac_nome'  => ['label' => 'Pacote mensal — nome',  'value' => 'Pacote mensal cabelo + barba'],
        'pac_desc'  => ['label' => 'Pacote mensal — descrição', 'value' => 'Duas visitas por mês, pra quem é de casa'],
        'pac_preco' => ['label' => 'Pacote mensal — preço', 'value' => 'R$160'],
    ],

    // ── BARBEIROS ────────────────────────────────────────────────
    'barbeiros' => [
        'eyebrow' => ['label' => 'Barbeiros — olho', 'value' => 'Quem corta'],
        'titulo'  => ['label' => 'Barbeiros — título', 'value' => 'Os barbeiros'],
        'b1_papel' => ['label' => 'Barbeiro 1 — papel', 'value' => 'Dono e barbeiro · desde 2015'],
        'b1_nome'  => ['label' => 'Barbeiro 1 — nome', 'value' => 'Frioli'],
        'b1_bio'   => ['label' => 'Barbeiro 1 — bio', 'value' => 'Toca a casa desde o primeiro dia. É quem pega a referência que você traz e traduz no corte que o seu cabelo aguenta. Onze anos de cadeira e uma base de clientes que virou amizade.'],
        'b1_tags'  => ['label' => 'Barbeiro 1 — especialidades (vírgula)', 'value' => 'Corte masculino, Corte infantil, Barba'],
        'b2_papel' => ['label' => 'Barbeiro 2 — papel', 'value' => 'Barbeiro'],
        'b2_nome'  => ['label' => 'Barbeiro 2 — nome', 'value' => 'Tiago'],
        'b2_bio'   => ['label' => 'Barbeiro 2 — bio', 'value' => 'Chegou pra somar na Frioli Hair, com o mesmo padrão de acabamento da casa. Faz também barboterapia. Cadeira nova, agenda aberta pra te atender.'],
        'b2_tags'  => ['label' => 'Barbeiro 2 — especialidades (vírgula)', 'value' => 'Corte masculino, Corte infantil, Barba'],
    ],

    // ── TRABALHOS ────────────────────────────────────────────────
    'trabalhos' => [
        'eyebrow' => ['label' => 'Trabalhos — olho', 'value' => 'Portfólio'],
        'titulo'  => ['label' => 'Trabalhos — título', 'value' => 'Trabalhos da casa'],
    ],

    // ── AVALIAÇÕES ───────────────────────────────────────────────
    'avaliacoes' => [
        'eyebrow' => ['label' => 'Avaliações — olho', 'value' => 'O que dizem'],
        'titulo'  => ['label' => 'Avaliações — título', 'value' => 'Nota cheia no Google'],
        'rotulo'  => ['label' => 'Avaliações — legenda da nota', 'value' => 'em 72 avaliações no Google'],
        'r1_t' => ['label' => 'Avaliação 1 — texto', 'value' => 'Melhor barbearia de jundiaí!! Preço justo, qualidade excelente!! Não me arrependo e volto sempre!'],
        'r1_a' => ['label' => 'Avaliação 1 — autor', 'value' => 'Pedro Pistarini'],
        'r2_t' => ['label' => 'Avaliação 2 — texto', 'value' => 'Lugar agradavel. Atendimento excelente. Cortes bem definidos e desenhos incríveis.'],
        'r2_a' => ['label' => 'Avaliação 2 — autor', 'value' => 'Felipe Andrade'],
        'r3_t' => ['label' => 'Avaliação 3 — texto', 'value' => 'O Frioli além de cortar o cabelo exepcionalmente é um cara sensacional, recomendo muito, espaço sensacional e trampo impecavel!'],
        'r3_a' => ['label' => 'Avaliação 3 — autor', 'value' => 'Diogo Manzato'],
        'r4_t' => ['label' => 'Avaliação 4 — texto', 'value' => 'Salão nota 1.000 super organizado, ambiente legal e com ótimos profissionais.'],
        'r4_a' => ['label' => 'Avaliação 4 — autor', 'value' => 'Emily Vitória'],
        'r5_t' => ['label' => 'Avaliação 5 — texto', 'value' => 'Ambiente muito agradável e profissionais excelentes.'],
        'r5_a' => ['label' => 'Avaliação 5 — autor', 'value' => 'Luiz Jacinto'],
    ],

    // ── LOCAL ────────────────────────────────────────────────────
    'local' => [
        'eyebrow'   => ['label' => 'Local — olho', 'value' => 'Onde ficamos'],
        'titulo'    => ['label' => 'Local — título', 'value' => 'Passa na Frioli'],
        'instagram' => ['label' => 'Local — Instagram (handle)', 'value' => '@frioli.hair'],
        'horario'   => ['label' => 'Local — horário (HTML, <br> entre linhas)', 'value' => 'Seg a qui · 8h30 às 20h<br>Sexta · 9h às 20h30<br>Sábado · 9h30 às 17h<br><span class="fh-dom">domingo fechado</span>'],
    ],

    // ── CTA FINAL ────────────────────────────────────────────────
    'cta' => [
        'titulo' => ['label' => 'CTA final — título', 'value' => 'Bora marcar teu corte?'],
        'texto'  => ['label' => 'CTA final — texto', 'value' => 'Escolhe o horário que te serve e aparece. A gente resolve o resto na cadeira.'],
        'btn'    => ['label' => 'CTA final — botão', 'value' => 'Agendar agora'],
    ],

    // ── JORNADA: LOGIN ───────────────────────────────────────────
    'login' => [
        'titulo' => ['label' => 'Login — título do card', 'value' => 'Entrar'],
    ],

    // ── JORNADA: AGENDAR ─────────────────────────────────────────
    'agendar' => [
        'titulo' => ['label' => 'Agendar — título', 'value' => 'Agendar horário'],
        'intro'  => ['label' => 'Agendar — subtítulo', 'value' => 'Escolha o barbeiro, o serviço, o dia e um horário livre.'],
    ],

    // ── JORNADA: CHECKOUT ────────────────────────────────────────
    'checkout' => [
        'titulo' => ['label' => 'Checkout — título', 'value' => 'Pagamento'],
        'intro'  => ['label' => 'Checkout — texto do botão/ação', 'value' => 'Ir para o pagamento'],
        'nota'   => ['label' => 'Checkout — aviso', 'value' => 'Você será direcionado ao ambiente seguro de pagamento para finalizar com cartão (à vista ou parcelado) ou Pix.'],
    ],

    // ── JORNADA: RETORNO DE PAGAMENTO ────────────────────────────
    'retorno' => [
        'titulo' => ['label' => 'Retorno — título (aguardando)', 'value' => 'Aguardando confirmação do pagamento'],
        'msg'    => ['label' => 'Retorno — mensagem (aguardando)', 'value' => 'Estamos confirmando seu pagamento com a operadora — em geral leva menos de 1 minuto. Avisaremos por WhatsApp assim que for confirmado.'],
    ],

    // ── JORNADA: VERIFICAÇÃO DE WHATSAPP ─────────────────────────
    'whatsapp' => [
        'cadastrar_titulo' => ['label' => 'Cadastrar WhatsApp — título do card', 'value' => 'Confirmar número de WhatsApp'],
        'cadastrar_texto'  => ['label' => 'Cadastrar WhatsApp — texto', 'value' => 'Para continuar, precisamos verificar o seu número de WhatsApp.<br>Informe abaixo e enviaremos um código de confirmação.'],
        'verificar_titulo' => ['label' => 'Verificar WhatsApp — título do card', 'value' => 'Verificar WhatsApp'],
    ],
];
