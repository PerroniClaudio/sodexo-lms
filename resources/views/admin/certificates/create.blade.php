<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuovo attestato')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.certificates.store') }}" enctype="multipart/form-data" class="flex flex-col gap-6">
                    @csrf

                    @include('admin.certificates.partials.form', [
                        'requireTemplateUpload' => true,
                    ])

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.certificates.index') }}" class="btn btn-ghost">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva e continua') }}</span>
                            <x-lucide-arrow-right class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
