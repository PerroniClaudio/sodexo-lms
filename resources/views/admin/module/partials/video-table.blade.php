<script type="module" src="https://unpkg.com/@mux/mux-player@latest/dist/mux-player.js"></script>
@vite('resources/js/admin-module-video.js')

<div class="card border border-base-300 bg-base-100 shadow-sm mb-6" data-module-id="{{ $module->id }}" data-assigned-video-id="{{ $module->video_id }}">
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
                      <span class="font-semibold text-base-content/70 text-sm">{{ __('Validità modulo') }}</span>
                      <span id="selected-video-validity" class="text-sm"></span>
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
