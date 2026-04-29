<form method="POST" action="{{ route('admin.videos.store') }}" enctype="multipart/form-data" id="video-upload-form">
    @csrf
    <div id="video-upload-success" class="alert alert-success hidden mb-2"></div>
    <div class="form-control flex flex-col gap-2 mb-2">
        <label for="title" class="label p-0">
            <span class="label-text font-medium">Titolo video</span>
        </label>
        <input type="text" name="title" id="title" class="input input-bordered w-full" required>
    </div>
    <div class="form-control flex flex-col gap-2 mb-2">
        <label for="description" class="label p-0">
            <span class="label-text font-medium">Descrizione</span>
        </label>
        <textarea name="description" id="description" class="textarea textarea-bordered min-h-32 w-full"></textarea>
    </div>
    <div class="form-control flex flex-col gap-2 mb-2">
        <label for="video_file" class="label p-0">
            <span class="label-text font-medium">File video</span>
        </label>
        <div class="w-full">
            <label for="video_file" id="video-drop-label" class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer transition hover:border-primary bg-base-100">
                <svg class="w-8 h-8 text-base-content/60 mb-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16v4a2 2 0 002 2h6a2 2 0 002-2v-4M7 16l-4-4m0 0l4-4m-4 4h18" /></svg>
                <span class="text-sm">Trascina qui il file video o clicca per selezionare</span>
                <span class="mt-1 text-xs text-primary" id="video-file-name"></span>
                <input type="file" name="video_file" id="video_file" class="hidden" accept="video/*" required>
            </label>
        </div>
        <div class="w-full mt-2">
            <div id="video-upload-progress" class="hidden">
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div id="video-upload-bar" class="bg-primary h-2.5 rounded-full" style="width:0%"></div>
                </div>
                <div class="text-xs mt-1 flex justify-between">
                    <span id="video-upload-percent">0%</span>
                    <span id="video-upload-eta"></span>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary mt-2">Carica video</button>
<script>
function initVideoUploadForm() {
    var dropLabel = document.getElementById('video-drop-label');
    var fileInput = document.getElementById('video_file');
    var fileNameSpan = document.getElementById('video-file-name');
    var uploadForm = document.getElementById('video-upload-form');
    var successAlert = document.getElementById('video-upload-success');
    var progressWrap = document.getElementById('video-upload-progress');
    var progressBar = document.getElementById('video-upload-bar');
    var progressPercent = document.getElementById('video-upload-percent');
    var progressEta = document.getElementById('video-upload-eta');
    if (!dropLabel || !fileInput || !fileNameSpan || !uploadForm) return;

    // Blocca sempre il comportamento di default su drag&drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(event) {
        dropLabel.addEventListener(event, function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (event === 'dragenter' || event === 'dragover') {
                dropLabel.classList.add('border-primary');
            } else {
                dropLabel.classList.remove('border-primary');
            }
        });
    });

    // Gestione drop file: aggiorna solo il nome, non assegna direttamente fileInput.files (non supportato su tutti i browser)
    dropLabel.addEventListener('drop', function(e) {
        if (e.dataTransfer.files && e.dataTransfer.files.length) {
            // Crea un nuovo oggetto DataTransfer per forzare l'evento change
            var dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            fileInput.files = dt.files;
            var event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        }
    });

    // Clicca sull'input file quando si clicca sull'area
    dropLabel.addEventListener('click', function(e) {
        if (e.target === fileInput) return;
        fileInput.click();
    });

    // Gestione selezione manuale e visualizzazione nome file
    fileInput.addEventListener('change', function(e) {
        if (fileInput.files && fileInput.files.length) {
            fileNameSpan.textContent = fileInput.files[0].name;
        } else {
            fileNameSpan.textContent = '';
        }
    });

    // Upload video in due step: 1) invia metadati, 2) upload file su Mux
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData();
        formData.append('title', uploadForm.title.value);
        formData.append('description', uploadForm.description.value);
        formData.append('video_file', uploadForm.video_file.files[0]);
        formData.append('_token', uploadForm._token.value);
        successAlert.classList.add('hidden');
        if (progressWrap) {
            progressWrap.classList.remove('hidden');
            progressBar.style.width = '0%';
            progressPercent.textContent = '0%';
            progressEta.textContent = '';
        }
        // Step 1: invia metadati e ottieni upload_url
        fetch(uploadForm.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': formData.get('_token'),
            },
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.upload_url) throw new Error('Errore upload');
            // Step 2: upload file su Mux
            var muxXhr = new XMLHttpRequest();
            var muxStart = Date.now();
            muxXhr.open('PUT', data.upload_url, true);
            // Imposta Content-Type raw del file
            var file = uploadForm.video_file.files[0];
            muxXhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
            muxXhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressPercent.textContent = percent + '%';
                    var elapsed = (Date.now() - muxStart) / 1000;
                    var speed = e.loaded / elapsed;
                    var eta = speed > 0 ? (e.total - e.loaded) / speed : 0;
                    if (eta > 0) {
                        var min = Math.floor(eta / 60);
                        var sec = Math.round(eta % 60);
                        progressEta.textContent = 'ETA: ' + (min > 0 ? min + 'm ' : '') + sec + 's';
                    } else {
                        progressEta.textContent = '';
                    }
                }
            });
            muxXhr.onload = function() {
                if (muxXhr.status === 200 || muxXhr.status === 201) {
                    successAlert.textContent = 'Video caricato! Sarà visibile in Mux Assets dopo la transcodifica.';
                    successAlert.classList.remove('hidden');
                    uploadForm.reset();
                    fileNameSpan.textContent = '';
                    if (progressWrap) progressWrap.classList.add('hidden');
                    if (window.refreshVideoSelect) window.refreshVideoSelect();
                } else {
                    successAlert.textContent = 'Errore durante l\'upload su Mux.';
                    successAlert.classList.remove('hidden');
                    if (progressWrap) progressWrap.classList.add('hidden');
                }
            };
            muxXhr.onerror = function() {
                successAlert.textContent = 'Errore durante l\'upload su Mux.';
                successAlert.classList.remove('hidden');
                if (progressWrap) progressWrap.classList.add('hidden');
            };
            // Invia il file come raw binary
            muxXhr.send(file);
        })
        .catch(function() {
            successAlert.textContent = 'Errore durante il caricamento.';
            successAlert.classList.remove('hidden');
            if (progressWrap) progressWrap.classList.add('hidden');
        });
    });
}
</script>
</form>
