@props([
    'course',
    'courseCertificateTemplates',
    'courseValidator',
    'customCertificateTypeLabels',
    'updateUrl',
])

@php
    $submittedType = old('type');
@endphp

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div>
                <h2 class="card-title">{{ __('Template attestati del corso') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Carica un template DOCX dedicato a questo corso direttamente da questa pagina. L\'upload attiva una nuova versione del template per il tipo selezionato.') }}
                </p>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                @foreach ($customCertificateTypeLabels as $type => $label)
                    @php
                        $templateState = $courseCertificateTemplates[$type] ?? ['specific' => null, 'resolved' => null];
                        $specificCertificate = $templateState['specific'];
                        $resolvedCertificate = $templateState['resolved'];
                        $showErrors = $submittedType === $type;
                    @endphp

                    <div class="rounded-box border border-base-300 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold">{{ $label }}</h3>
                                <p class="mt-1 text-sm text-base-content/70">
                                    @if ($specificCertificate !== null)
                                        {{ __('Per questo corso è attivo un template specifico.') }}
                                    @elseif ($resolvedCertificate !== null)
                                        {{ __('Per questo corso è attualmente in uso il template generico attivo.') }}
                                    @else
                                        {{ __('Nessun template attivo per questo tipo.') }}
                                    @endif
                                </p>
                            </div>

                            @if ($resolvedCertificate !== null)
                                <a href="{{ route('admin.certificates.edit', $resolvedCertificate) }}" class="btn btn-outline btn-sm">
                                    <x-lucide-settings class="h-4 w-4" />
                                    {{ __('Gestisci') }}
                                </a>
                            @endif
                        </div>

                        <div class="mt-4 space-y-2 rounded-box border border-base-300 bg-base-200/40 p-4 text-sm">
                            @if ($specificCertificate !== null)
                                <p class="font-medium">{{ __('Template specifico attivo') }}</p>
                                <p class="text-base-content/70">{{ $specificCertificate->original_filename }}</p>
                            @elseif ($resolvedCertificate !== null)
                                <p class="font-medium">{{ __('Template generico in uso') }}</p>
                                <p class="text-base-content/70">{{ $resolvedCertificate->original_filename }}</p>
                            @else
                                <p class="text-base-content/70">{{ __('Non è presente alcun template attivo.') }}</p>
                            @endif
                        </div>

                        <form method="POST" action="{{ $updateUrl }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="type" value="{{ $type }}">

                            <label for="template_{{ $type }}" class="label p-0">
                                <span class="label-text font-medium">{{ __('Nuovo template DOCX') }}</span>
                            </label>
                            <input
                                id="template_{{ $type }}"
                                name="template"
                                type="file"
                                accept=".docx"
                                class="file-input file-input-bordered w-full {{ $showErrors && $errors->has('template') ? 'file-input-error' : '' }}"
                                required
                            >

                            @if ($showErrors)
                                @error('type')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                                @error('template')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            @endif

                            <div class="flex justify-end">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <span>{{ __('Carica template') }}</span>
                                    <x-lucide-upload class="h-4 w-4" />
                                </button>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>