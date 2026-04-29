<div class="card border border-base-300 bg-base-100 shadow-sm mb-6">
    <div class="card-body gap-4">
        <h2 class="text-lg font-semibold mb-2">{{ __('Info video selezionato') }}</h2>
        <div id="selected-video-preview" class="mb-4">
            <div id="selected-video-loading" class="text-base-content/60 text-sm hidden">{{ __('Caricamento...') }}</div>
            <div id="selected-video-empty" class="text-base-content/60 text-sm">{{ __('Nessun video selezionato') }}</div>
            <div id="selected-video-details" class="hidden flex flex-col gap-2 md:flex-row md:items-center md:gap-6">
                <div class="flex flex-col items-center gap-2">
                    <img id="selected-video-thumb" src="" alt="Anteprima video" class="w-48 h-32 object-cover rounded border border-base-300" loading="lazy" />
                    <button type="button" class="btn btn-outline btn-xs mt-1" id="selected-video-preview-btn">{{ __('Guarda anteprima') }}</button>
                </div>
                <div class="flex-1 min-w-0 w-full self-start grid grid-cols-1 xl:grid-cols-2 gap-2">
                    <div class="flex flex-col">
                      <span class="font-semibold text-base-content/70 text-sm">{{ __('Titolo') }}</span>
                      <span id="selected-video-title" class="input input-xs w-full text-sm"></span>
                    </div>
                    <div class="flex flex-col">
                      <span class="font-semibold text-base-content/70 text-sm">{{ __('Durata') }}</span>
                      <span id="selected-video-duration" class="input input-xs w-full text-sm"></span>
                    </div>
                    <div class="flex flex-col">
                      <span class="font-semibold text-base-content/70 text-sm">{{ __('Stato') }}</span>
                      <span id="selected-video-status" class="input input-xs w-full text-sm"></span>
                    </div>
                    <div class="flex flex-col">
                      <span class="font-semibold text-base-content/70 text-sm">{{ __('Moduli che lo usano') }}</span>
                      <span id="selected-video-modules-count" class="input input-xs w-full text-sm"></span>
                    </div>
                    <div class="flex flex-col">
                      <span class="font-semibold text-base-content/70 text-sm">{{ __('Descrizione') }}</span>
                      <span id="selected-video-description" class="textarea textarea-xs w-full text-sm"></span>
                    </div>
                    <span id="selected-video-trashed" class="badge badge-error hidden">{{ __('Eliminato') }}</span>
                </div>
            </div>
        </div>
        <h2 class="text-lg font-semibold mb-2">{{ __('Libreria Video') }}</h2>
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                <div class="w-full max-w-md">
                    <label class="input input-bordered flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-base-content/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" /></svg>
                        <input type="search" id="module-video-search" class="grow" placeholder="{{ __('Cerca titolo o stato...') }}">
                    </label>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="openModuleVideoUploadModal()">
                        <span>{{ __('Carica nuovo') }}</span>
                        <svg xmlns='http://www.w3.org/2000/svg' class='h-4 w-4' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 4v16m8-8H4'/></svg>
                    </button>
                </div>
            </div>
        </div>

        <template id="module-video-upload-modal-template">
            <div id="module-video-upload-modal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
                <div class="bg-base-100 p-6 rounded shadow-lg relative w-full max-w-xl flex flex-col gap-4">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-lg font-semibold">{{ __('Carica nuovo video') }}</h2>
                        <button onclick="closeModuleVideoUploadModal()" class="btn btn-sm btn-circle ml-2">✕</button>
                    </div>
                    <div class="mt-2">
                        @include('admin.videos.partials.upload-form')
                    </div>
                </div>
            </div>
        </template>
        <script>
        // Carica i dati del video selezionato
        function fetchSelectedVideo(videoId) {
            const loading = document.getElementById('selected-video-loading');
            const empty = document.getElementById('selected-video-empty');
            const details = document.getElementById('selected-video-details');
            if (!videoId) {
                if (loading) loading.classList.add('hidden');
                if (details) details.classList.add('hidden');
                if (empty) empty.classList.remove('hidden');
                return;
            }
            if (loading) loading.classList.remove('hidden');
            if (details) details.classList.add('hidden');
            if (empty) empty.classList.add('hidden');
            fetch(`/admin/api/videos/${videoId}`)
                .then(r => r.json())
                .then(video => {
                    if (loading) loading.classList.add('hidden');
                    if (!video || video.error) {
                        if (details) details.classList.add('hidden');
                        if (empty) empty.classList.remove('hidden');
                        return;
                    }
                    if (details) details.classList.remove('hidden');
                    if (empty) empty.classList.add('hidden');
                    document.getElementById('selected-video-thumb').src = `/admin/videos/${video.id}/signed-thumbnail`;
                    document.getElementById('selected-video-title').textContent = video.title || '';
                    document.getElementById('selected-video-description').textContent = video.description || '';
                    document.getElementById('selected-video-duration').textContent = video.duration ? `${formatDuration(video.duration)}` : '';
                    document.getElementById('selected-video-status').textContent = video.mux_video_status ? `${video.mux_video_status}` : '';
                    document.getElementById('selected-video-modules-count').textContent = `${video.modules_count || 0}`;
                    const trashed = document.getElementById('selected-video-trashed');
                    if (trashed) {
                        if (video.trashed_at) trashed.classList.remove('hidden');
                        else trashed.classList.add('hidden');
                    }
                    // Preview
                    document.getElementById('selected-video-preview-btn').onclick = function() {
                        openModuleVideoPreview(video.id);
                    };
                })
                .catch(() => {
                    if (loading) loading.classList.add('hidden');
                    if (details) details.classList.add('hidden');
                    if (empty) empty.classList.remove('hidden');
                });
        }

        function formatDuration(seconds) {
            if (!seconds || isNaN(seconds)) return '';
            const min = Math.floor(seconds / 60);
            const sec = seconds % 60;
            return `${min}m ${sec}s`;
        }
        function openModuleVideoUploadModal() {
            let modal = document.getElementById('module-video-upload-modal');
            if (!modal) {
                const tpl = document.getElementById('module-video-upload-modal-template');
                document.body.appendChild(tpl.content.cloneNode(true));
                modal = document.getElementById('module-video-upload-modal');
            }
            modal.classList.remove('hidden');
            if (typeof initVideoUploadForm === 'function') {
                initVideoUploadForm();
            }
            // Aggiorna la tabella video dopo upload completato
            const uploadForm = document.getElementById('video-upload-form');
            if (uploadForm) {
                uploadForm.onsubmit = function(e) {
                    e.preventDefault();
                    const formData = new FormData(uploadForm);
                    fetch(uploadForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.id || data.success) {
                            closeModuleVideoUploadModal();
                            fetchModuleVideos();
                        } else {
                            alert('Errore caricamento video');
                        }
                    });
                };
            }
        }
        function closeModuleVideoUploadModal() {
            const modal = document.getElementById('module-video-upload-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }
        </script>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-base-300 text-sm">
                <thead>
                    <tr class="bg-base-200">
                        <th class="px-4 py-2 cursor-pointer" data-sort="title">{{ __('Titolo') }}</th>
                        <th class="px-4 py-2 cursor-pointer" data-sort="modules_count">{{ __('Utilizzato da moduli') }}</th>
                        <th class="px-4 py-2">{{ __('Anteprima') }}</th>
                        <th class="px-4 py-2 cursor-pointer" data-sort="mux_video_status">{{ __('Stato') }}</th>
                        <th class="px-4 py-2 cursor-pointer" data-sort="status">{{ __('Attivo') }}</th>
                        <th class="px-4 py-2">{{ __('Azioni') }}</th>
                    </tr>
                </thead>
                <tbody id="module-video-table-body">
                    <!-- Popolato via JS -->
                </tbody>
            </table>
        </div>
        <div class="flex justify-between items-center mt-2">
            <button id="module-video-prev-page" class="btn btn-sm" disabled>{{ __('Precedente') }}</button>
            <span id="module-video-pagination-info" class="text-xs"></span>
            <button id="module-video-next-page" class="btn btn-sm" disabled>{{ __('Successiva') }}</button>
        </div>
    </div>
</div>

<template id="module-video-row-template">
    <tr class="hover:bg-base-200">
        <td class="px-4 py-2 font-medium"></td>
        <td class="px-4 py-2"></td>
        <td class="px-4 py-2">
            <button type="button" class="hover:cursor-pointer" data-preview-btn>
                <img class="w-24 h-16 object-cover rounded border border-base-300" loading="lazy" />
            </button>
        </td>
        <td class="px-4 py-2"></td>
        <td class="px-4 py-2"></td>
        <td class="px-4 py-2">
            <button type="button" class="btn btn-success btn-xs" data-assign-btn>{{ __('Assegna') }}</button>
            <button type="button" class="btn btn-error btn-xs" data-unassign-btn>{{ __('Rimuovi') }}</button>
        </td>
    </tr>
</template>

<template id="module-video-preview-modal-template">
    <div id="module-video-preview-modal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" style="display:none;">
        <div class="bg-base-100 p-6 rounded shadow-lg relative w-full max-w-xl flex flex-col gap-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-lg font-semibold">{{ __('Anteprima') }}</h2>
                <button onclick="closeModuleVideoPreview()" class="btn btn-sm btn-circle ml-2">✕</button>
            </div>
            <div data-mux-player-container class="mt-2"></div>
        </div>
    </div>
</template>
<script type="module" src="https://unpkg.com/@mux/mux-player@latest/dist/mux-player.js"></script>
<script>
// Stato tabella video modulo
let moduleVideoState = {
    page: 1,
    search: '',
    sort: 'created_at',
    direction: 'desc',
    moduleId: @json($module->id),
    assignedVideoId: @json($module->video_id),
};

function fetchModuleVideos() {
        // Aggiorna preview video selezionato
        fetchSelectedVideo(moduleVideoState.assignedVideoId);
    const params = new URLSearchParams({
        page: moduleVideoState.page,
        search: moduleVideoState.search,
        sort: moduleVideoState.sort,
        direction: moduleVideoState.direction,
    });
    fetch(`/admin/api/videos?${params}`)
        .then(r => r.json())
        .then(data => renderModuleVideoTable(data));
}

function renderModuleVideoTable(data) {
    const tbody = document.getElementById('module-video-table-body');
    tbody.innerHTML = '';
    data.data.forEach(video => {
        const tpl = document.getElementById('module-video-row-template');
        const row = tpl.content.cloneNode(true);
        const tds = row.querySelectorAll('td');
        tds[0].textContent = video.title;
        tds[1].textContent = video.modules_count;
        const img = row.querySelector('img');
        img.src = `/admin/videos/${video.id}/signed-thumbnail`;
        img.alt = 'Anteprima video';
        row.querySelector('[data-preview-btn]').onclick = () => openModuleVideoPreview(video.id);
        tds[3].textContent = video.mux_video_status;
        tds[4].innerHTML = video.trashed_at ? '<span class="badge badge-outline badge-error">Eliminato</span>' : '<span class="badge badge-outline badge-success">Attivo</span>';
        // Azioni
        const assignBtn = row.querySelector('[data-assign-btn]');
        const unassignBtn = row.querySelector('[data-unassign-btn]');
        if (moduleVideoState.assignedVideoId == video.id) {
            assignBtn.classList.add('hidden');
            unassignBtn.classList.remove('hidden');
        } else {
            assignBtn.classList.remove('hidden');
            unassignBtn.classList.add('hidden');
        }
        assignBtn.onclick = () => assignVideoToModule(video.id);
        unassignBtn.onclick = () => unassignVideoFromModule();
        tbody.appendChild(row);
    });
    // Paginazione
    document.getElementById('module-video-pagination-info').textContent = `Pagina ${data.current_page} di ${data.last_page}`;
    document.getElementById('module-video-prev-page').disabled = data.current_page === 1;
    document.getElementById('module-video-next-page').disabled = data.current_page === data.last_page;
}

document.getElementById('module-video-search').addEventListener('input', function(e) {
    moduleVideoState.search = e.target.value;
    moduleVideoState.page = 1;
    fetchModuleVideos();
});
document.getElementById('module-video-prev-page').addEventListener('click', function() {
    if (moduleVideoState.page > 1) {
        moduleVideoState.page--;
        fetchModuleVideos();
    }
});
document.getElementById('module-video-next-page').addEventListener('click', function() {
    moduleVideoState.page++;
    fetchModuleVideos();
});
document.querySelectorAll('th[data-sort]').forEach(th => {
    th.addEventListener('click', function() {
        const sort = th.getAttribute('data-sort');
        if (moduleVideoState.sort === sort) {
            moduleVideoState.direction = moduleVideoState.direction === 'asc' ? 'desc' : 'asc';
        } else {
            moduleVideoState.sort = sort;
            moduleVideoState.direction = 'asc';
        }
        fetchModuleVideos();
    });
});

function assignVideoToModule(videoId) {
    // Chiamata API per assegnare video al modulo
    fetch(`/admin/api/modules/${moduleVideoState.moduleId}/assign-video`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ video_id: videoId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            moduleVideoState.assignedVideoId = videoId;
            fetchModuleVideos();
            fetchSelectedVideo(videoId);
        } else {
            alert('Errore assegnazione video');
        }
    });
}
function unassignVideoFromModule() {
    fetch(`/admin/api/modules/${moduleVideoState.moduleId}/unassign-video`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            moduleVideoState.assignedVideoId = null;
            fetchModuleVideos();
            fetchSelectedVideo(null);
        } else {
            alert('Errore rimozione video');
        }
    });
}

function openModuleVideoPreview(videoId) {
    fetch(`/admin/videos/${videoId}/signed-playback-url`)
        .then(r => r.json())
        .then(data => {
            if (data.playback_id && data.token) {
                showModuleVideoModalWithMuxPlayer(data.playback_id, data.token);
            } else {
                alert('Impossibile generare la preview');
            }
        });
}
function showModuleVideoModalWithMuxPlayer(playbackId, token) {
    let modal = document.getElementById('module-video-preview-modal');
    if (!modal) {
        const tpl = document.getElementById('module-video-preview-modal-template');
        document.body.appendChild(tpl.content.cloneNode(true));
        modal = document.getElementById('module-video-preview-modal');
    }
    const playerContainer = modal.querySelector('[data-mux-player-container]');
    playerContainer.innerHTML = '';
    const muxPlayer = document.createElement('mux-player');
    muxPlayer.setAttribute('stream-type', 'on-demand');
    muxPlayer.setAttribute('playback-id', playbackId);
    muxPlayer.setAttribute('token', token);
    muxPlayer.setAttribute('metadata-video-title', 'Anteprima video');
    muxPlayer.setAttribute('primary-color', '#2563eb');
    muxPlayer.setAttribute('accent-color', '#2563eb');
    muxPlayer.setAttribute('auto-play', 'true');
    muxPlayer.setAttribute('style', 'width:100%;height:320px;border-radius:8px;');
    playerContainer.appendChild(muxPlayer);
    modal.classList.remove('hidden');
    modal.style.display = '';
}
function closeModuleVideoPreview() {
    const modal = document.getElementById('module-video-preview-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
        const playerContainer = modal.querySelector('[data-mux-player-container]');
        playerContainer.innerHTML = '';
    }
}

document.addEventListener('DOMContentLoaded', fetchModuleVideos);
document.addEventListener('DOMContentLoaded', function() {
    fetchModuleVideos();
    fetchSelectedVideo(moduleVideoState.assignedVideoId);
});
</script>
