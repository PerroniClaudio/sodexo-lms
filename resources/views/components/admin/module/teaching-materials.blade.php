@props(['data' => []])

@php
    extract($data);
@endphp

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Materiale didattico') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('Carica immagini, PDF o presentazioni PPTX disponibili per gli utenti del modulo.') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.courses.modules.teaching-materials.store', [$course, $module]) }}" enctype="multipart/form-data" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <label class="form-control w-full">
                <span class="label">
                    <span class="label-text">{{ __('File') }}</span>
                </span>
                <input type="file" name="materials[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.pptx,image/*,application/pdf,application/vnd.openxmlformats-officedocument.presentationml.presentation" class="file-input file-input-bordered w-full" />
                @error('materials')
                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                @enderror
                @error('materials.*')
                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                @enderror
            </label>
            <button type="submit" class="btn btn-primary sm:mb-0">
                <x-lucide-upload class="h-4 w-4" />
                <span>{{ __('Carica') }}</span>
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Tipo') }}</th>
                        <th>{{ __('Dimensione') }}</th>
                        <th>{{ __('Caricato il') }}</th>
                        <th class="text-right">{{ __('Azioni') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($module->teachingMaterials as $material)
                        <tr>
                            <td class="max-w-xs truncate font-medium">{{ $material->original_name }}</td>
                            <td>{{ $material->mime_type ?: __('Sconosciuto') }}</td>
                            <td>{{ Illuminate\Support\Number::fileSize($material->size_bytes) }}</td>
                            <td>{{ $material->uploaded_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.courses.modules.teaching-materials.download', [$course, $module, $material]) }}" class="btn btn-outline btn-sm">
                                        <x-lucide-download class="h-4 w-4" />
                                        <span>{{ __('Scarica') }}</span>
                                    </a>
                                    <form method="POST" action="{{ route('admin.courses.modules.teaching-materials.destroy', [$course, $module, $material]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-error btn-outline btn-sm">
                                            <x-lucide-trash-2 class="h-4 w-4" />
                                            <span>{{ __('Elimina') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-sm text-base-content/60">{{ __('Nessun materiale didattico caricato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
