// Stato tabella video modulo
const wrapper = document.querySelector('[data-module-id]');
let moduleVideoState = {
    page: 1,
    search: '',
    sort: 'created_at',
    direction: 'desc',
    moduleId: wrapper ? parseInt(wrapper.dataset.moduleId) : null,
    assignedVideoId: wrapper ? parseInt(wrapper.dataset.assignedVideoId) : null,
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
    // Usa src con URL Mux firmato
    muxPlayer.setAttribute('src', `https://stream.mux.com/${playbackId}.m3u8?token=${token}`);
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
// Espone la funzione per l'onclick nel markup
window.closeModuleVideoPreview = closeModuleVideoPreview;

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
            
            // Carica validità modulo
            fetchModuleValidity();
            
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

function fetchModuleValidity() {
    const validityElement = document.getElementById('selected-video-validity');
    if (!validityElement || !moduleVideoState.moduleId) return;
    
    fetch(`/admin/api/modules/${moduleVideoState.moduleId}/validity`)
        .then(r => r.json())
        .then(data => {
            validityElement.innerHTML = '';
            if (data.isValid) {
                const badge = document.createElement('span');
                badge.className = 'badge badge-sm badge-success';
                badge.textContent = 'Valido';
                validityElement.appendChild(badge);
            } else {
                const badge = document.createElement('span');
                badge.className = 'badge badge-sm badge-error';
                badge.textContent = 'Non valido';
                validityElement.appendChild(badge);
                
                if (data.errors && data.errors.length > 0) {
                    const errorText = document.createElement('span');
                    errorText.className = 'text-xs text-error ml-2';
                    errorText.textContent = data.errors.join(' ');
                    validityElement.appendChild(errorText);
                }
            }
        })
        .catch(() => {
            validityElement.innerHTML = '<span class="text-xs text-base-content/60">-</span>';
        });
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
        uploadForm.onsubmit = null; // azzera eventuali handler precedenti
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
// Espone la funzione per l'onclick nel markup
window.openModuleVideoUploadModal = openModuleVideoUploadModal;

function closeModuleVideoUploadModal() {
    const modal = document.getElementById('module-video-upload-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}
// Espone la funzione per l'onclick nel markup
window.closeModuleVideoUploadModal = closeModuleVideoUploadModal;

document.addEventListener('DOMContentLoaded', function() {
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
    fetchModuleVideos();
    fetchSelectedVideo(moduleVideoState.assignedVideoId);
});