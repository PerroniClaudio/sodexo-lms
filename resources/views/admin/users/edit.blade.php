<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica utente')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')
                    @include('admin.users.partials.form')
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">
                            {{ __('Annulla') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Aggiorna') }}</span>
                            <x-lucide-save class="h-4 w-4" />
                        </button>
                    </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const typeSelect = document.getElementById('account_type');
                        const userOnlyBlocks = document.querySelectorAll('[data-user-only]');
                        function toggleUserOnlyFields() {
                            if (!typeSelect) return;
                            if (typeSelect.value === 'user') {
                                userOnlyBlocks.forEach(block => {
                                    block.style.display = '';
                                    block.querySelectorAll('input,select,textarea').forEach(el => {
                                        if (el.dataset.originalName) {
                                            el.name = el.dataset.originalName;
                                        }
                                        el.disabled = false;
                                    });
                                });
                            } else {
                                userOnlyBlocks.forEach(block => {
                                    block.style.display = 'none';
                                    block.querySelectorAll('input,select,textarea').forEach(el => {
                                        if (!el.dataset.originalName) {
                                            el.dataset.originalName = el.name;
                                        }
                                        el.removeAttribute('name');
                                        el.disabled = true;
                                    });
                                });
                            }
                        }
                        if (typeSelect) {
                            toggleUserOnlyFields();
                            typeSelect.addEventListener('change', toggleUserOnlyFields);
                        }
                    });
                </script>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
