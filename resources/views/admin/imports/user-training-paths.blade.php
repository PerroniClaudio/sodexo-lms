<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Associazione utenti percorsi formativi')">
            <x-slot:actions>
                <a href="{{ route('admin.imports.user-training-paths.template') }}" class="btn btn-outline">
                    <x-lucide-download class="h-4 w-4" />
                    <span>{{ __('Scarica template') }}</span>
                </a>
                @if ((session('active_role') ?? auth()->user()?->getRoleNames()->first()) === 'superadmin')
                    <a href="{{ route('admin.importazioni-monitor.index') }}" class="btn btn-outline">
                        <x-lucide-list-checks class="h-4 w-4" />
                        <span>{{ __('Monitor importazioni') }}</span>
                    </a>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <div class="alert alert-success">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Carica file Excel') }}</h2>
                </div>

                <form method="POST" action="{{ route('admin.imports.user-training-paths.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <label for="file" class="label p-0">
                            <span class="label-text font-medium">{{ __('File Excel') }}</span>
                        </label>
                        <input
                            id="file"
                            type="file"
                            name="file"
                            accept=".xlsx,.xls"
                            class="file-input file-input-bordered mt-2 w-full @error('file') file-input-error @enderror"
                        >
                        <p class="mt-2 text-xs text-base-content/60">
                            {{ __('Formati supportati: .xlsx e .xls') }}
                        </p>
                        @error('file')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
                        <p class="font-medium">{{ __('Regole applicate') }}</p>
                        <ul class="mt-3 list-disc space-y-2 pl-5">
                            <li>{{ __('Prima colonna obbligatoria: codice fiscale utente esistente.') }}</li>
                            <li>{{ __('Seconda colonna obbligatoria: codice percorso formativo esistente e pubblicato.') }}</li>
                            <li>{{ __('Se iscrizione al percorso esiste già, import riallinea anche le iscrizioni ai corsi collegati.') }}</li>
                            <li>{{ __('Se iscrizione al percorso era stata eliminata, import la ripristina.') }}</li>
                        </ul>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <x-lucide-file-up class="h-4 w-4" />
                            <span>{{ __('Avvia importazione') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            data-user-training-path-import-status-card
            data-status-url="{{ route('admin.imports.user-training-paths.status-card') }}"
        >
            @include('admin.imports.partials.user-training-paths-status-card', ['recentImports' => $recentImports])
        </div>

        <dialog class="modal" data-training-path-import-approvals-modal>
            <div class="modal-box max-w-5xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold">{{ __('Approva iscrizioni non idonee') }}</h3>
                        <p class="text-sm text-base-content/70">{{ __('Approvando, tutti i corsi vengono assegnati ma quelli indicati non bloccano né contano nel percorso.') }}</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-training-path-import-approvals aria-label="{{ __('Chiudi') }}">✕</button>
                </div>

                <div class="mt-6 hidden items-center justify-center py-10" data-training-path-import-approvals-loader>
                    <span class="loading loading-spinner loading-lg"></span>
                </div>

                <div class="mt-6 overflow-x-auto rounded-box border border-base-300" data-training-path-import-approvals-table>
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('Utente') }}</th>
                                <th>{{ __('Percorso') }}</th>
                                <th>{{ __('Corsi e motivi') }}</th>
                                <th>{{ __('Decisione') }}</th>
                            </tr>
                        </thead>
                        <tbody data-training-path-import-approvals-body></tbody>
                    </table>
                </div>

                <p class="mt-6 hidden rounded-box border border-dashed border-base-300 p-6 text-center text-sm text-base-content/60" data-training-path-import-approvals-empty>
                    {{ __('Nessuna approvazione in attesa.') }}
                </p>

                <template data-training-path-import-approval-row-template>
                    <tr>
                        <td>
                            <div class="font-medium" data-cell="user-name"></div>
                            <div class="text-xs text-base-content/60" data-cell="user-meta"></div>
                        </td>
                        <td>
                            <div class="font-medium" data-cell="path-title"></div>
                            <div class="text-xs text-base-content/60" data-cell="path-code"></div>
                        </td>
                        <td><div class="space-y-3" data-cell="courses"></div></td>
                        <td>
                            <div class="flex flex-col gap-2">
                                <button type="button" class="btn btn-success btn-sm" data-action="approve">{{ __('Approva') }}</button>
                                <button type="button" class="btn btn-ghost btn-sm" data-action="reject">{{ __('Non approvare') }}</button>
                            </div>
                        </td>
                    </tr>
                </template>

                <div class="modal-action mt-6">
                    <button type="button" class="btn btn-ghost" data-close-training-path-import-approvals>{{ __('Chiudi') }}</button>
                    <button type="button" class="btn btn-warning" data-approve-all-training-path-import>{{ __('Approva tutti') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button type="submit">{{ __('Chiudi') }}</button></form>
        </dialog>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const statusCard = document.querySelector('[data-user-training-path-import-status-card]');

                if (! statusCard) {
                    return;
                }

                const statusUrl = statusCard.dataset.statusUrl;
                const approvalModal = document.querySelector('[data-training-path-import-approvals-modal]');
                const approvalBody = approvalModal?.querySelector('[data-training-path-import-approvals-body]');
                const approvalTemplate = approvalModal?.querySelector('[data-training-path-import-approval-row-template]');
                const approvalLoader = approvalModal?.querySelector('[data-training-path-import-approvals-loader]');
                const approvalTable = approvalModal?.querySelector('[data-training-path-import-approvals-table]');
                const approvalEmpty = approvalModal?.querySelector('[data-training-path-import-approvals-empty]');
                const approveAllButton = approvalModal?.querySelector('[data-approve-all-training-path-import]');
                let approvalUrls = null;

                const refreshStatusCard = async function () {
                    try {
                        const response = await fetch(statusUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (! response.ok) {
                            return;
                        }

                        statusCard.innerHTML = await response.text();
                    } catch (error) {
                        console.error(error);
                    }
                };

                const renderApprovals = function (groups) {
                    approvalBody.innerHTML = '';

                    groups.forEach(function (group) {
                        const fragment = approvalTemplate.content.cloneNode(true);
                        const userName = `${group.user.surname || ''} ${group.user.name || ''}`.trim();
                        fragment.querySelector('[data-cell="user-name"]').textContent = userName || `#${group.user.id}`;
                        fragment.querySelector('[data-cell="user-meta"]').textContent = `${group.user.fiscal_code || '-'} · ${group.user.email || '-'}`;
                        fragment.querySelector('[data-cell="path-title"]').textContent = group.training_path.title || '-';
                        fragment.querySelector('[data-cell="path-code"]').textContent = group.training_path.code || '-';

                        const courses = fragment.querySelector('[data-cell="courses"]');
                        group.courses.forEach(function (course) {
                            const block = document.createElement('div');
                            block.className = 'rounded-box border border-warning/30 bg-warning/10 p-3';
                            const title = document.createElement('p');
                            title.className = 'font-medium';
                            title.textContent = `${course.title || '-'} (${course.code || '-'})`;
                            const reasons = document.createElement('ul');
                            reasons.className = 'mt-2 list-disc space-y-1 pl-5 text-xs';
                            (course.reasons || []).forEach(function (reason) {
                                const item = document.createElement('li');
                                item.textContent = reason;
                                reasons.appendChild(item);
                            });
                            block.append(title, reasons);
                            courses.appendChild(block);
                        });

                        const decide = async function (approved, button) {
                            button.disabled = true;
                            try {
                                await window.axios.post(approvalUrls.decision, {
                                    user_id: group.user.id,
                                    training_path_id: group.training_path.id,
                                    approved,
                                }, { headers: { Accept: 'application/json' } });
                                await loadApprovals();
                                await refreshStatusCard();
                            } catch (error) {
                                window.alert(error.response?.data?.message || 'Errore durante il salvataggio della decisione.');
                            } finally {
                                button.disabled = false;
                            }
                        };

                        const approveButton = fragment.querySelector('[data-action="approve"]');
                        const rejectButton = fragment.querySelector('[data-action="reject"]');
                        approveButton.addEventListener('click', () => void decide(true, approveButton));
                        rejectButton.addEventListener('click', () => void decide(false, rejectButton));
                        approvalBody.appendChild(fragment);
                    });

                    approvalTable.classList.toggle('hidden', groups.length === 0);
                    approvalEmpty.classList.toggle('hidden', groups.length > 0);
                    approveAllButton.classList.toggle('hidden', groups.length === 0);
                };

                const loadApprovals = async function () {
                    approvalLoader.classList.remove('hidden');
                    approvalLoader.classList.add('flex');
                    approvalTable.classList.add('hidden');
                    approvalEmpty.classList.add('hidden');

                    try {
                        const response = await window.axios.get(approvalUrls.index, { headers: { Accept: 'application/json' } });
                        renderApprovals(response.data.data || []);
                    } catch (error) {
                        window.alert(error.response?.data?.message || 'Errore durante il caricamento delle approvazioni.');
                    } finally {
                        approvalLoader.classList.add('hidden');
                        approvalLoader.classList.remove('flex');
                    }
                };

                statusCard.addEventListener('click', function (event) {
                    const button = event.target.closest('[data-open-training-path-import-approvals]');
                    if (!button) return;

                    approvalUrls = {
                        index: button.dataset.approvalsUrl,
                        decision: button.dataset.decisionUrl,
                        approveAll: button.dataset.approveAllUrl,
                    };
                    approvalModal.showModal();
                    void loadApprovals();
                });

                approvalModal?.querySelectorAll('[data-close-training-path-import-approvals]').forEach(function (button) {
                    button.addEventListener('click', () => approvalModal.close());
                });

                approveAllButton?.addEventListener('click', async function () {
                    if (!approvalUrls) return;
                    approveAllButton.disabled = true;
                    try {
                        await window.axios.post(approvalUrls.approveAll, {}, { headers: { Accept: 'application/json' } });
                        await loadApprovals();
                        await refreshStatusCard();
                    } catch (error) {
                        window.alert(error.response?.data?.message || 'Errore durante l\'approvazione di tutte le iscrizioni.');
                    } finally {
                        approveAllButton.disabled = false;
                    }
                });

                window.setInterval(refreshStatusCard, 3000);
            });
        </script>
    @endpush
</x-layouts.admin>
