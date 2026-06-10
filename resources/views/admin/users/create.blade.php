<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuovo utente')" />

        @php
            $activeUserEditSection = 'user';
        @endphp

        {{-- @if ($errors->any())
            <div class="alert alert-error mb-4">
                <ul class="list-disc pl-6">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif --}}
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-col gap-6">
                    @csrf

                    <div class="flex flex-col gap-4 border-b border-base-300 pb-6 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-2xl font-semibold text-base-content">{{ __('Utente') }}</h2>
                            <p class="mt-2 text-sm text-base-content/65">{{ __('Account, anagrafica e dati personali principali.') }}</p>
                        </div>
                    </div>

                    <x-admin.users.forms.user-fields />
                    <x-admin.users.forms.residence-fields />
                    <x-admin.users.forms.work-fields
                        :job-categories="$jobCategories"
                        :job-levels="$jobLevels"
                        :job-tasks="$jobTasks"
                        :job-roles="$jobRoles"
                        :job-sectors="$jobSectors"
                        :job-units="$jobUnits"
                    />
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">
                            {{ __('Annulla') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva e continua') }}</span>
                            <x-lucide-arrow-right class="h-4 w-4" />
                        </button>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const typeSelect = document.getElementById('account_type');
                            const userOnlyBlocks = document.querySelectorAll('[data-user-only-block]');
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
                                            if (el.dataset.required === 'true') {
                                                el.required = true;
                                            }
                                        });
                                    });
                                } else {
                                    userOnlyBlocks.forEach(block => {
                                        block.style.display = 'none';
                                        block.querySelectorAll('input,select,textarea').forEach(el => {
                                            if (!el.dataset.originalName) {
                                                el.dataset.originalName = el.name;
                                            }
                                            el.required = false;
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
