<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica ente finanziatore')">
            <x-slot:actions>
                @if($fundingEntity->trashed())
                    <form method="POST" action="{{ route('admin.funding-entities.restore', $fundingEntity->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-success btn-outline">{{ __('Ripristina') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.funding-entities.destroy', $fundingEntity) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questo ente finanziatore?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error btn-outline">{{ __('Elimina') }}</button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.funding-entities.update', $fundingEntity) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    @include('admin.funding-entity.partials.form-fields', ['fundingEntity' => $fundingEntity])

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.funding-entities.index') }}" class="btn btn-ghost">{{ __('Annulla') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Salva modifiche') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
