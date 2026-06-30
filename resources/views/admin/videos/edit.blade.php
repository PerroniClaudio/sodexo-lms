<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica video')">
            <x-slot:actions>
                <a href="{{ route('admin.videos.index') }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna alla libreria') }}</span>
                </a>
            </x-slot:actions>
        </x-page-header>

        @if (session('error'))
            <div class="alert alert-error">
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-6">
                    <div>
                        <h2 class="card-title">{{ $video->title }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Aggiorna i metadati del video dalla libreria.') }}
                        </p>
                    </div>

                    @if ($isLocked)
                        <div class="rounded-box border border-warning/30 bg-warning/10 p-4 text-sm text-base-content/80">
                            {{ __('Questo video è già utilizzato in uno o più moduli. Per evitare incoerenze non può essere modificato o sostituito.') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.videos.update', $video) }}" class="grid gap-6">
                        @csrf
                        @method('PUT')

                        <div class="form-control flex flex-col gap-2">
                            <label for="title" class="label p-0">
                                <span class="label-text font-medium">{{ __('Titolo') }}</span>
                            </label>
                            <input
                                id="title"
                                name="title"
                                type="text"
                                value="{{ old('title', $video->title) }}"
                                class="input input-bordered w-full @error('title') input-error @enderror"
                                @disabled($isLocked)
                                @readonly($isLocked)
                            >
                            @error('title')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-control flex flex-col gap-2">
                            <label for="description" class="label p-0">
                                <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
                                @disabled($isLocked)
                                @readonly($isLocked)
                            >{{ old('description', $video->description) }}</textarea>
                            @error('description')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary" @disabled($isLocked)>
                                <x-lucide-save class="h-4 w-4" />
                                <span>{{ __('Salva video') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="card-title">{{ __('Anteprima') }}</h2>

                    @if ($video->mux_playback_id && $video->mux_video_status === 'ready')
                        <button type="button" class="overflow-hidden rounded-box border border-base-300" onclick="openVideoPreview({{ $video->id }})">
                            <img
                                src="{{ route('admin.videos.signed-thumbnail', $video) }}"
                                alt="{{ __('Anteprima video') }}"
                                class="h-44 w-full object-cover"
                                loading="lazy"
                            >
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="openVideoPreview({{ $video->id }})">
                            {{ __('Guarda anteprima') }}
                        </button>
                    @else
                        <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
                            {{ __('Anteprima non disponibile finchÃ© il video non Ã¨ pronto su Mux.') }}
                        </div>
                    @endif

                    <div class="space-y-2 text-sm">
                        <p><span class="font-medium">{{ __('Stato') }}:</span> {{ $video->mux_video_status }}</p>
                        <p><span class="font-medium">{{ __('Durata') }}:</span> {{ $video->duration_seconds ? gmdate('H:i:s', $video->duration_seconds) : __('N/D') }}</p>
                        <p><span class="font-medium">{{ __('Moduli che lo usano') }}:</span> {{ $video->modules_count }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template id="video-preview-modal-template">
        <div id="video-preview-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60">
            <div class="relative flex w-full max-w-xl flex-col gap-4 rounded bg-base-100 p-6 shadow-lg">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">{{ __('Preview') }}</h2>
                    <button onclick="closeVideoPreview()" class="btn btn-sm btn-circle ml-2">x</button>
                </div>
                <div data-mux-player-container class="mt-2"></div>
            </div>
        </div>
    </template>

    <script type="module" src="https://unpkg.com/@mux/mux-player@latest/dist/mux-player.js"></script>
    <script>
        function ensureVideoPreviewModal() {
            if (document.getElementById('video-preview-modal')) {
                return document.getElementById('video-preview-modal');
            }

            const template = document.getElementById('video-preview-modal-template');
            document.body.appendChild(template.content.cloneNode(true));

            return document.getElementById('video-preview-modal');
        }

        function openVideoPreview(videoId) {
            fetch(`/admin/videos/${videoId}/signed-playback-url`)
                .then((response) => response.json())
                .then((data) => {
                    if (!data.playback_id || !data.token) {
                        alert('Impossibile generare la preview');

                        return;
                    }

                    const modal = ensureVideoPreviewModal();
                    const playerContainer = modal.querySelector('[data-mux-player-container]');

                    playerContainer.innerHTML = '';

                    const muxPlayer = document.createElement('mux-player');
                    muxPlayer.setAttribute('stream-type', 'on-demand');
                    muxPlayer.setAttribute('playback-id', data.playback_id);
                    muxPlayer.setAttribute('token', data.token);
                    muxPlayer.setAttribute('metadata-video-title', '{{ __('Anteprima video') }}');
                    muxPlayer.setAttribute('primary-color', '#2563eb');
                    muxPlayer.setAttribute('accent-color', '#2563eb');
                    muxPlayer.setAttribute('auto-play', 'true');
                    muxPlayer.setAttribute('style', 'width:100%;height:320px;border-radius:8px;');
                    muxPlayer.setAttribute('playbackrates', '1');
                    muxPlayer.setAttribute('disablepictureinpicture', '');
                    playerContainer.appendChild(muxPlayer);

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                })
                .catch(() => {
                    alert('Impossibile generare la preview');
                });
        }

        function closeVideoPreview() {
            const modal = document.getElementById('video-preview-modal');

            if (!modal) {
                return;
            }

            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.querySelector('[data-mux-player-container]').innerHTML = '';
        }
    </script>
</x-layouts.admin>
