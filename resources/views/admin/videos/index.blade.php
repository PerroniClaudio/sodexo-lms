<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'title', 'label' => __('Titolo'), 'sortable' => true],
            ['key' => 'modules_count', 'label' => __('Utilizzato da moduli'), 'sortable' => true],
            ['key' => 'preview', 'label' => __('Preview'), 'sortable' => false],
            ['key' => 'mux_video_status', 'label' => __('Stato elaborazione'), 'sortable' => true],
            ['key' => 'status', 'label' => __('Attivo/Eliminato'), 'sortable' => true],
            ['key' => 'actions', 'label' => __('Azioni'), 'sortable' => false],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="mb-4 flex flex-col gap-3">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-1">
                    <h1 class="text-3xl font-semibold text-base-content">{{ __('Libreria Video') }}</h1>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="btn btn-primary" onclick="openVideoUploadModal()">
                        <span>{{ __('Carica nuovo') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    <button type="button" id="sync-mux-btn" class="btn btn-outline" onclick="syncMuxVideosStatus()">
                        <span>{{ __('Sincronizza Mux') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                </div>
            </div>
            <hr class="border-base-300">
        </div>

        <template id="video-upload-modal-template">
            <div id="video-upload-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60">
                <div class="relative flex w-full max-w-xl flex-col gap-4 rounded bg-base-100 p-6 shadow-lg">
                    <div class="mb-2 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">{{ __('Carica nuovo video') }}</h2>
                        <button onclick="closeVideoUploadModal()" class="btn btn-sm btn-circle ml-2">x</button>
                    </div>
                    <div class="mt-2">
                        @include('admin.videos.partials.upload-form')
                    </div>
                </div>
            </div>
        </template>

        <x-data-table
            :columns="$columns"
            :rows="$videos"
            :sort="$sort ?? ''"
            :direction="$direction ?? 'asc'"
            :search="$search ?? ''"
            :search-placeholder="__('Cerca titolo o stato')"
            :empty-message="__('Nessun video trovato.')"
            :show-search="false"
        >
            <x-slot:filters>
                <form method="GET" action="{{ route('admin.videos.index') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input
                                type="checkbox"
                                name="show_trashed"
                                value="1"
                                class="checkbox"
                                @checked(request('show_trashed'))
                                onchange="this.form.submit()"
                            >
                            <span class="label-text">{{ __('Mostra eliminati') }}</span>
                        </label>
                    </div>

                    <div class="flex w-full max-w-md items-center gap-2">
                        @foreach (request()->query() as $key => $value)
                            @continue(in_array($key, ['search', 'page', 'show_trashed'], true))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                name="search"
                                value="{{ $search ?? '' }}"
                                class="grow"
                                placeholder="{{ __('Cerca titolo o stato') }}"
                            >
                        </label>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Cerca') }}
                        </button>
                    </div>
                </form>
            </x-slot:filters>

            @foreach ($videos as $video)
                <tr class="hover:bg-base-200">
                    <td>{{ $video->title }}</td>
                    <td>{{ $video->modules_count ?? $video->modules()->count() }}</td>
                    <td>
                        @if ($video->mux_playback_id && $video->mux_video_status === 'ready')
                            <button type="button" class="hover:cursor-pointer" onclick="openVideoPreview({{ $video->id }})">
                                <img
                                    src="{{ route('admin.videos.signed-thumbnail', $video) }}"
                                    alt="{{ __('Anteprima video') }}"
                                    class="h-16 w-24 rounded border border-base-300 object-cover"
                                    loading="lazy"
                                    onerror="this.style.display='none'"
                                />
                            </button>
                        @else
                            <span class="text-xs text-gray-500">{{ __('Non disponibile') }}</span>
                        @endif
                    </td>
                    <td>{{ $video->mux_video_status }}</td>
                    <td>
                        @if ($video->trashed())
                            <span class="badge badge-outline badge-error h-fit">{{ __('Eliminato') }}</span>
                        @else
                            <span class="badge badge-outline badge-success h-fit">{{ __('Attivo') }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-2">
                            @if (! $video->trashed())
                                <a href="{{ route('admin.videos.edit', $video) }}" class="btn btn-primary btn-sm">
                                    {{ __('Modifica') }}
                                </a>
                                <form action="{{ route('admin.videos.destroy', $video) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-error btn-sm" onclick="return confirm('{{ __('Sei sicuro?') }}')">
                                        {{ __('Elimina') }}
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.videos.restore', $video->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        {{ __('Ripristina') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach

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
                function openVideoUploadModal() {
                    let modal = document.getElementById('video-upload-modal');

                    if (!modal) {
                        const template = document.getElementById('video-upload-modal-template');
                        document.body.appendChild(template.content.cloneNode(true));
                        modal = document.getElementById('video-upload-modal');
                    }

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');

                    if (typeof initVideoUploadForm === 'function') {
                        initVideoUploadForm();
                    }
                }

                function closeVideoUploadModal() {
                    const modal = document.getElementById('video-upload-modal');

                    if (!modal) {
                        return;
                    }

                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }

                function syncMuxVideosStatus() {
                    const btn = document.getElementById('sync-mux-btn');
                    const originalHtml = btn.innerHTML;

                    btn.disabled = true;
                    btn.innerHTML = '<span class="loading loading-spinner loading-sm"></span><span>{{ __('Sincronizzazione...') }}</span>';

                    fetch('{{ route('admin.videos.sync-mux-status') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                    })
                        .then((response) => response.json())
                        .then((data) => {
                            if (data.success) {
                                alert('✓ ' + data.message);
                                setTimeout(() => window.location.reload(), 2000);

                                return;
                            }

                            alert('⚠ ' + data.message);
                        })
                        .catch((error) => {
                            alert('Errore durante la sincronizzazione: ' + error.message);
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.innerHTML = originalHtml;
                        });
                }

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

                            showVideoPreviewModal(data.playback_id, data.token);
                        })
                        .catch(() => {
                            alert('Impossibile generare la preview');
                        });
                }

                function showVideoPreviewModal(playbackId, token) {
                    const modal = ensureVideoPreviewModal();
                    const playerContainer = modal.querySelector('[data-mux-player-container]');

                    playerContainer.innerHTML = '';

                    const muxPlayer = document.createElement('mux-player');
                    muxPlayer.setAttribute('stream-type', 'on-demand');
                    muxPlayer.setAttribute('playback-id', playbackId);
                    muxPlayer.setAttribute('token', token);
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

            <div class="mt-4">
                {{ $videos->links() }}
            </div>
        </x-data-table>
    </div>
</x-layouts.admin>
