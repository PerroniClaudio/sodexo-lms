<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Download di prova attestato')"
            :description="__('Genera un DOCX di prova con i dati selezionati e i fallback configurati.')"
        />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="alert alert-info">
                    <span>{{ __('La preview non richiede completamento corso o quiz. I dati mancanti usano i fallback definiti.') }}</span>
                </div>

                <form method="POST" action="{{ route('admin.certificates.preview-download', $certificate) }}" class="flex flex-col gap-6">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="form-control flex flex-col gap-2">
                            <label for="course_id" class="label p-0">
                                <span class="label-text font-medium">{{ __('Corso') }}</span>
                            </label>
                            <select id="course_id" name="course_id" class="select select-bordered w-full @error('course_id') select-error @enderror" required>
                                <option value="">{{ __('Seleziona un corso') }}</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->title }}</option>
                                @endforeach
                            </select>
                            @error('course_id')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-control flex flex-col gap-2">
                            <label for="user_id" class="label p-0">
                                <span class="label-text font-medium">{{ __('Utente') }}</span>
                            </label>
                            <select id="user_id" name="user_id" class="select select-bordered w-full @error('user_id') select-error @enderror" required>
                                <option value="">{{ __('Seleziona un utente') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>
                                        {{ trim($user->name.' '.$user->surname) }}@if($user->fiscal_code) - {{ $user->fiscal_code }}@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="card border border-base-300 bg-base-200/40 shadow-sm">
                        <div class="card-body gap-4">
                            <h2 class="card-title text-base">{{ __('Variabili disponibili') }}</h2>
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach ($placeholders as $placeholder => $description)
                                    <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                        <p class="font-mono text-sm font-semibold">{{ $placeholder }}</p>
                                        <p class="text-sm text-base-content/70">{{ $description }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.certificates.edit', $certificate) }}" class="btn btn-ghost">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <x-lucide-download class="h-4 w-4" />
                            <span>{{ __('Scarica DOCX di prova') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
