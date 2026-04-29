<div>
    <label for="video_id" class="block font-medium mb-2">Video associato</label>
    <input type="text" id="video-search" class="input input-bordered w-full mb-2" placeholder="Cerca per titolo..." autocomplete="off">
    <select name="video_id" id="video_id" class="form-select w-full">
        <option value="">-- Seleziona un video dalla libreria --</option>
        @foreach($videos as $video)
            <option value="{{ $video->id }}" data-title="{{ Str::lower($video->title) }}" @if(old('video_id', $module->video_id) == $video->id) selected @endif>
                {{ $video->title }}
            </option>
        @endforeach
    </select>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('video-search');
        var select = document.getElementById('video_id');
        if (!searchInput || !select) return;
        searchInput.addEventListener('input', function() {
            var term = searchInput.value.trim().toLowerCase();
            for (var i = 0; i < select.options.length; i++) {
                var opt = select.options[i];
                if (i === 0) { opt.hidden = false; continue; } // lascia sempre la prima opzione visibile
                var title = opt.getAttribute('data-title') || '';
                opt.hidden = term && !title.includes(term);
            }
            // Se c'è solo una opzione visibile (oltre la prima), selezionala automaticamente
            var visible = Array.from(select.options).filter(function(opt, idx) { return idx > 0 && !opt.hidden; });
            if (visible.length === 1) {
                visible[0].selected = true;
            }
        });
    });
    </script>
    <div class="mt-2">
        <a href="#" class="btn btn-sm btn-secondary" onclick="event.preventDefault(); document.getElementById('upload-video-form-modal').classList.toggle('hidden');">Carica nuovo video</a>
    </div>

    @if($module->video)
        <div class="mt-6">
            <label class="block font-medium mb-2">Preview video</label>
            <div>
                <video controls width="480" src="{{ route('admin.videos.signed-playback', $module->video) }}"></video>
            </div>
            <div class="text-xs text-gray-500 mt-1">La preview utilizza un signed playback URL Mux.</div>
        </div>
    @endif
</div>

<!-- Modal upload video separato dal form principale -->
<div id="upload-video-form-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-base-100 p-6 rounded-lg shadow-lg w-full max-w-md relative">
        <button class="absolute top-2 right-2 btn btn-sm btn-circle" onclick="document.getElementById('upload-video-form-modal').classList.add('hidden');">✕</button>
        <h2 class="font-bold text-lg mb-4">Carica nuovo video</h2>
        @include('admin.videos.partials.upload-form')
    </div>

