@extends('layouts.app')
@section('title', 'Galeria de Fotos')
@section('style')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .drop-zone {
            border: 2px dashed var(--marrom);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            color: var(--bege);
            cursor: pointer;
            transition: background .2s;
        }
        .drop-zone.dragover { background: rgba(201,163,111,.12); }
        .drop-zone input[type=file] { display: none; }

        #preview-grid { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        #preview-grid .prev-item { position: relative; width: 110px; height: 80px; border-radius: 6px; overflow: hidden; }
        #preview-grid .prev-item img { width: 100%; height: 100%; object-fit: cover; }
        #preview-grid .prev-item button {
            position: absolute; top: 2px; right: 2px;
            background: rgba(0,0,0,.6); color: #fff; border: none; border-radius: 50%;
            width: 20px; height: 20px; font-size: .6rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }

        .foto-card { background: var(--bs-tertiary-bg); border: 1px solid var(--bs-border-color); border-radius: 10px; overflow: hidden; }
        .foto-card img { width: 100%; height: 180px; object-fit: cover; display: block; }
        .foto-card .drag-handle { cursor: grab; color: var(--cinza); font-size: 1.2rem; }
        .foto-card.sortable-ghost { opacity: .4; }
        .save-status { font-size: .72rem; }
    </style>
@endsection
@section('main')
<div class="container py-4">
    <nav class="mb-2"><small class="text-secondary"><a href="{{ route('admin.index') }}" class="text-secondary">Painel</a> / Galeria de Fotos</small></nav>
    <h3 class="mb-1" style="color: var(--marrom);">Galeria de Fotos</h3>
    <p class="text-secondary mb-4">Adicione, remova ou reordene as fotos. Arraste os cartões para mudar a ordem (vale para o carrossel e a seção "Nossa casa").</p>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2">
            {{ session('success') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- UPLOAD --}}
    <div class="card mb-4">
        <div class="card-header fw-bold"><i class="bi bi-cloud-upload"></i> Adicionar fotos</div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.fotos.upload') }}" enctype="multipart/form-data" id="upload-form">
                @csrf
                <div class="drop-zone" id="drop-zone" onclick="document.getElementById('file-input').click()">
                    <i class="bi bi-image fs-2 d-block mb-2"></i>
                    <span>Clique ou arraste imagens aqui</span><br>
                    <small class="text-secondary">JPG, PNG, WebP · máx. 5 MB por foto</small>
                    <input type="file" id="file-input" name="imagens[]" multiple accept="image/*">
                </div>
                <div id="preview-grid"></div>
                <button type="submit" class="btn mt-3" id="btn-upload" style="display:none; background-color: var(--marrom); color:#1a1410">
                    <i class="bi bi-cloud-upload"></i> Enviar fotos
                </button>
            </form>
            @error('imagens.*')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- GALERIA ATUAL --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="bi bi-grid"></i> Fotos atuais ({{ $fotos->count() }})</span>
            @if($fotos->count() > 1)<span class="text-secondary small"><i class="bi bi-arrows-move"></i> Arraste para reordenar</span>@endif
        </div>
        <div class="card-body">
            @if($fotos->isEmpty())
                <p class="text-secondary text-center py-4">Nenhuma foto na galeria ainda.</p>
            @else
                <div class="row g-4" id="sortable-grid">
                    @foreach($fotos as $foto)
                    <div class="col-md-4 col-sm-6" data-id="{{ $foto->id }}">
                        <div class="foto-card">
                            <div class="d-flex align-items-center px-2 pt-2 pb-1 gap-2">
                                <span class="drag-handle"><i class="bi bi-grip-vertical"></i></span>
                                <span class="save-status text-secondary small flex-grow-1 text-end"></span>
                            </div>
                            <img src="/storage/galeria/{{ $foto->filename }}" alt="{{ $foto->title ?? 'Foto' }}">
                            <div class="p-2">
                                <input type="text" class="form-control form-control-sm mb-2 foto-field" data-id="{{ $foto->id }}" data-field="title" placeholder="Título da foto…" value="{{ $foto->title }}">
                                <textarea class="form-control form-control-sm mb-2 foto-field" data-id="{{ $foto->id }}" data-field="description" rows="2" placeholder="Descrição…">{{ $foto->description }}</textarea>
                                <form method="POST" action="{{ route('admin.fotos.delete', $foto) }}" onsubmit="return confirm('Remover esta foto?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-trash"></i> Remover</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
@section('scriptEnd')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
    // ── Drag-and-drop upload ──
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    let selectedFiles = [];

    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragover'); addFiles([...e.dataTransfer.files]); });
    fileInput.addEventListener('change', () => addFiles([...fileInput.files]));

    function addFiles(files) {
        files.filter(f => f.type.startsWith('image/')).forEach(f => {
            if (!selectedFiles.find(x => x.name === f.name && x.size === f.size)) selectedFiles.push(f);
        });
        renderPreviews();
    }

    function renderPreviews() {
        const grid = document.getElementById('preview-grid');
        grid.innerHTML = '';
        selectedFiles.forEach((f, i) => {
            const div = document.createElement('div');
            div.className = 'prev-item';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(f);
            const btn = document.createElement('button');
            btn.innerHTML = '<i class="bi bi-x"></i>';
            btn.onclick = () => { selectedFiles.splice(i, 1); renderPreviews(); };
            div.append(img, btn);
            grid.appendChild(div);
        });
        document.getElementById('btn-upload').style.display = selectedFiles.length ? 'inline-block' : 'none';
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        fileInput.files = dt.files;
    }

    const CSRF = '{{ csrf_token() }}';

    function statusEl(id) {
        return document.querySelector(`[data-id="${id}"]`)?.closest('.col-md-4, .col-sm-6')?.querySelector('.save-status');
    }
    function showStatus(id, msg, color = 'var(--bege)') {
        const el = statusEl(id); if (!el) return;
        el.style.color = color; el.textContent = msg;
    }
    async function patchFoto(id, data) {
        showStatus(id, 'Salvando…');
        try {
            const res = await fetch(`/admin/fotos/${id}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ _method: 'PATCH', ...data })
            });
            if (res.ok) showStatus(id, '✓ Salvo', '#3ddc84');
            else        showStatus(id, 'Erro!', '#dc3545');
        } catch { showStatus(id, 'Erro!', '#dc3545'); }
        setTimeout(() => { const el = statusEl(id); if (el) el.textContent = ''; }, 2500);
    }

    const debounceTimers = {};
    document.querySelectorAll('.foto-field').forEach(field => {
        field.addEventListener('input', () => {
            const id = field.dataset.id, fname = field.dataset.field;
            clearTimeout(debounceTimers[id + fname]);
            debounceTimers[id + fname] = setTimeout(() => patchFoto(id, { [fname]: field.value }), 800);
        });
    });

    const sortableGrid = document.getElementById('sortable-grid');
    if (sortableGrid) {
        Sortable.create(sortableGrid, { handle: '.drag-handle', animation: 150, ghostClass: 'sortable-ghost', onEnd: saveOrder });
    }
    async function saveOrder() {
        const cols = sortableGrid.querySelectorAll('[data-id]');
        const ordem = {};
        cols.forEach((el, idx) => { ordem[el.dataset.id] = idx; });
        await fetch('/admin/fotos/reorder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ ordem })
        });
    }
    </script>
@endsection
