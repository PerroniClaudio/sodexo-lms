<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica partner')">
            <x-slot:actions>
                <a href="{{ route('admin.partners.index') }}" class="btn btn-outline">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    {{ __('Torna alla lista') }}
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.partners.update', $partner) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <div class="form-control flex flex-col gap-2">
                        <label for="ragione_sociale" class="label p-0">
                            <span class="label-text font-medium">{{ __('Ragione sociale') }}</span>
                        </label>
                        <input id="ragione_sociale" name="ragione_sociale" type="text" value="{{ old('ragione_sociale', $partner->ragione_sociale) }}" class="input input-bordered w-full @error('ragione_sociale') input-error @enderror" required>
                        @error('ragione_sociale')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.partners.index') }}" class="btn btn-ghost">{{ __('Annulla') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Salva modifiche') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <h2 class="card-title">{{ __('Corsi associati') }}</h2>

                @if ($partner->courses->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
                        {{ __('Nessun corso associato a questo partner.') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Titolo') }}</th>
                                    <th>{{ __('Stato') }}</th>
                                    <th class="text-right">{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($partner->courses as $course)
                                    <tr>
                                        <td>{{ $course->getKey() }}</td>
                                        <td class="font-medium">{{ $course->title }}</td>
                                        <td><span class="badge badge-outline h-fit">{{ $course->status }}</span></td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                <a href="{{ route('admin.courses.edit', [$course, 'section' => 'partners']) }}" class="btn btn-outline btn-primary btn-sm">
                                                    {{ __('Apri corso') }}
                                                </a>
                                                <form method="POST" action="{{ route('admin.partners.courses.destroy', [$partner, $course]) }}" onsubmit="return confirm('{{ __('Rimuovere questa associazione?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-error btn-outline btn-sm">{{ __('Rimuovi') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
