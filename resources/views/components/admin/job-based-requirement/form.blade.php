@props(['data' => []])

@php
    extract($data);
@endphp

@php
    /** @var array<int, array<int, array{field: string, operator: string, value: mixed}>> $ruleGroups */
    $ruleGroups = old('rules', $jobBasedRequirement->rules ?? [
        [
            ['field' => 'job_role_id', 'operator' => 'IN', 'value' => []],
        ],
    ]);
@endphp

<div class="grid gap-6 md:grid-cols-1">
    <div class="form-control flex flex-col gap-2">
        <label for="name" class="label p-0">
            <span class="label-text font-medium">{{ __('Nome') }} <span class="text-error">*</span></span>
        </label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $jobBasedRequirement->name ?? '') }}"
            class="input input-bordered w-full @error('name') input-error @enderror"
            required
        >
        @error('name')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="form-control flex flex-col gap-2">
    <label for="description" class="label p-0">
        <span class="label-text font-medium">{{ __('Descrizione') }}</span>
    </label>
    <textarea
        id="description"
        name="description"
        rows="4"
        class="textarea textarea-bordered w-full @error('description') textarea-error @enderror"
    >{{ old('description', $jobBasedRequirement->description ?? '') }}</textarea>
    @error('description')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-control flex flex-col gap-2">
    <label class="label cursor-pointer justify-start gap-3">
        <input
            type="checkbox"
            name="is_active"
            value="1"
            class="checkbox"
            @checked(old('is_active', $jobBasedRequirement->is_active ?? true))
        >
        <span class="label-text font-medium">{{ __('Requisito attivo') }}</span>
    </label>
</div>

<div class="space-y-4">
    <div class="rounded-3xl border border-base-300 bg-base-200/40 p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold text-base-content">{{ __('Gruppi di applicazione') }}</h3>
                <p class="text-sm text-base-content/70">
                    {{ __('Ogni gruppo rappresenta un possibile caso in cui il requisito si applica. Se almeno un gruppo risulta vero, il requisito viene richiesto.') }}
                </p>
                <p class="text-sm text-base-content/70">
                    {{ __('All interno dello stesso gruppo puoi indicare uno o piu ruoli e una o piu mansioni. Se compili entrambi, l utente deve rientrare in entrambe le liste.') }}
                </p>
                <p class="text-xs uppercase tracking-wide text-base-content/50">
                    {{ __('Massimo 5 gruppi') }}
                </p>
            </div>

            <button type="button" class="btn btn-primary" data-add-rule-group>
                <x-lucide-plus class="h-4 w-4" />
                <span>{{ __('Aggiungi gruppo alternativo') }}</span>
            </button>
        </div>
    </div>

    <input type="hidden" name="rules_json" data-rules-json-input>

    <div class="space-y-4" data-rule-groups-root></div>

    <div class="rounded-3xl border border-primary/20 bg-primary/5 p-5" data-summary-root></div>

    @error('rules')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
    @error('rules.*')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
    @error('rules.*.*.field')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
    @error('rules.*.*.operator')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
    @error('rules.*.*.value')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>

<dialog class="modal" data-selector-modal>
    <div class="modal-box flex max-h-[min(46rem,calc(100vh-3rem))] w-11/12 max-w-6xl flex-col overflow-hidden rounded-[1.75rem] border border-base-300 bg-base-100 p-0 shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-base-300 px-6 py-5">
            <div>
                <h4 class="text-lg font-semibold text-base-content" data-selector-title>{{ __('Seleziona elementi') }}</h4>
                <p class="mt-1 text-sm text-base-content/70" data-selector-description></p>
            </div>
            <button type="button" class="btn btn-ghost btn-sm btn-circle" data-selector-close aria-label="{{ __('Chiudi') }}">
                <x-lucide-x class="h-4 w-4" />
            </button>
        </div>

        <div class="grid flex-1 gap-0 overflow-hidden lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
            <div class="flex min-h-0 flex-col border-b border-base-300 lg:border-b-0 lg:border-r">
                <div class="border-b border-base-300 px-6 py-4">
                    <label class="input input-bordered flex items-center gap-2">
                        <x-lucide-search class="h-4 w-4 text-base-content/50" />
                        <input type="search" class="grow" placeholder="{{ __('Cerca per nome') }}" data-selector-search>
                    </label>
                </div>

                <div class="min-h-0 flex-1 overflow-auto px-6 py-4">
                    <div class="overflow-x-auto rounded-2xl border border-base-300">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Nome') }}</th>
                                    <th class="w-32 text-right">{{ __('Azione') }}</th>
                                </tr>
                            </thead>
                            <tbody data-selector-results></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex min-h-0 flex-col bg-base-200/40">
                <div class="border-b border-base-300 px-6 py-4">
                    <h5 class="font-semibold text-base-content" data-selector-selected-title>{{ __('Selezionati') }}</h5>
                    <p class="mt-1 text-sm text-base-content/70" data-selector-selected-description></p>
                </div>

                <div class="min-h-0 flex-1 overflow-auto px-6 py-4">
                    <div class="flex flex-wrap gap-2" data-selector-selected-badges></div>
                    <div class="mt-4 space-y-2" data-selector-selected-list></div>
                </div>

                <div class="border-t border-base-300 px-6 py-4">
                    <button type="button" class="btn btn-primary w-full" data-selector-done>
                        {{ __('Conferma selezione') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-backdrop">
        <button type="button" data-selector-backdrop aria-label="{{ __('Chiudi') }}">{{ __('Chiudi') }}</button>
    </div>
</dialog>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-job-based-requirement-form]');

            if (!form) {
                return;
            }

            const maxGroups = 5;
            const groupsRoot = form.querySelector('[data-rule-groups-root]');
            const rulesJsonInput = form.querySelector('[data-rules-json-input]');
            const addGroupButton = form.querySelector('[data-add-rule-group]');
            const summaryRoot = form.querySelector('[data-summary-root]');
            const selectorModal = form.querySelector('[data-selector-modal]') ?? document.querySelector('[data-selector-modal]');
            const selectorTitle = selectorModal?.querySelector('[data-selector-title]');
            const selectorDescription = selectorModal?.querySelector('[data-selector-description]');
            const selectorSearch = selectorModal?.querySelector('[data-selector-search]');
            const selectorResults = selectorModal?.querySelector('[data-selector-results]');
            const selectorSelectedTitle = selectorModal?.querySelector('[data-selector-selected-title]');
            const selectorSelectedDescription = selectorModal?.querySelector('[data-selector-selected-description]');
            const selectorSelectedBadges = selectorModal?.querySelector('[data-selector-selected-badges]');
            const selectorSelectedList = selectorModal?.querySelector('[data-selector-selected-list]');
            const selectorClose = selectorModal?.querySelector('[data-selector-close]');
            const selectorDone = selectorModal?.querySelector('[data-selector-done]');
            const selectorBackdrop = selectorModal?.querySelector('[data-selector-backdrop]');
            const jobRoles = @json($jobRoles->map(fn ($jobRole) => ['id' => $jobRole->id, 'name' => $jobRole->name])->values());
            const jobTasks = @json($jobTasks->map(fn ($jobTask) => ['id' => $jobTask->id, 'name' => $jobTask->name])->values());
            const dictionaries = {
                role: jobRoles,
                task: jobTasks,
            };
            const dictionariesById = {
                role: new Map(jobRoles.map((item) => [Number(item.id), item])),
                task: new Map(jobTasks.map((item) => [Number(item.id), item])),
            };

            let state = normalizeState(@json($ruleGroups));
            let selectorContext = null;

            function normalizeIds(values) {
                const uniqueValues = Array.isArray(values)
                    ? values.map((value) => Number(value)).filter((value) => value > 0)
                    : (Number(values) > 0 ? [Number(values)] : []);

                return [...new Set(uniqueValues)];
            }

            function normalizeState(inputState) {
                if (!Array.isArray(inputState) || inputState.length === 0) {
                    return [{ roleIds: [], taskIds: [] }];
                }

                const normalizedGroups = inputState.map((group) => {
                    const normalizedGroup = { roleIds: [], taskIds: [] };

                    if (Array.isArray(group)) {
                        group.forEach((condition) => {
                            const field = condition?.field;
                            const value = normalizeIds(condition?.value ?? []);

                            if (field === 'job_role_id') {
                                normalizedGroup.roleIds = [...new Set([...normalizedGroup.roleIds, ...value])];
                            }

                            if (field === 'job_task_id') {
                                normalizedGroup.taskIds = [...new Set([...normalizedGroup.taskIds, ...value])];
                            }
                        });
                    }

                    return normalizedGroup;
                });

                return normalizedGroups.length > 0 ? normalizedGroups : [{ roleIds: [], taskIds: [] }];
            }

            function getSortedIds(type, values) {
                const dictionary = dictionariesById[type];

                return normalizeIds(values).sort((left, right) => {
                    const leftName = dictionary.get(left)?.name ?? '';
                    const rightName = dictionary.get(right)?.name ?? '';

                    return leftName.localeCompare(rightName, 'it', { sensitivity: 'base' });
                });
            }

            function escapeHtml(value) {
                return String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function serializeState() {
                return state
                    .map((group) => {
                        const conditions = [];
                        const roleIds = getSortedIds('role', group.roleIds);
                        const taskIds = getSortedIds('task', group.taskIds);

                        if (roleIds.length > 0) {
                            conditions.push({
                                field: 'job_role_id',
                                operator: 'IN',
                                value: roleIds,
                            });
                        }

                        if (taskIds.length > 0) {
                            conditions.push({
                                field: 'job_task_id',
                                operator: 'IN',
                                value: taskIds,
                            });
                        }

                        return conditions;
                    })
                    .filter((group) => group.length > 0);
            }

            function syncRulesJson() {
                rulesJsonInput.value = JSON.stringify(serializeState());
            }

            function getSelectedItems(type, groupIndex) {
                const group = state[groupIndex] ?? { roleIds: [], taskIds: [] };
                const ids = type === 'role' ? group.roleIds : group.taskIds;
                const dictionary = dictionariesById[type];

                return getSortedIds(type, ids)
                    .map((id) => dictionary.get(id))
                    .filter(Boolean);
            }

            function createSelectedBadges(type, groupIndex, emptyLabel) {
                const items = getSelectedItems(type, groupIndex);

                if (items.length === 0) {
                    return `<p class="text-sm text-base-content/60">${escapeHtml(emptyLabel)}</p>`;
                }

                return items
                    .map((item) => `
                        <span class="badge badge-outline gap-2 px-3 py-3">
                            <span>${escapeHtml(item.name)}</span>
                            <button
                                type="button"
                                class="rounded-full text-base-content/60 transition hover:text-error"
                                data-remove-selected
                                data-group-index="${groupIndex}"
                                data-type="${type}"
                                data-id="${item.id}"
                                aria-label="${escapeHtml(type === 'role' ? '{{ __('Rimuovi ruolo') }}' : '{{ __('Rimuovi mansione') }}')}"
                            >
                                &times;
                            </button>
                        </span>
                    `)
                    .join('');
            }

            function renderGroup(group, groupIndex) {
                const section = document.createElement('section');
                section.className = 'rounded-[1.75rem] border border-base-300 bg-base-100 p-5 shadow-sm';

                const groupLabel = groupIndex === 0 ? '{{ __('Questo gruppo') }}' : '{{ __('Oppure questo gruppo') }}';

                section.innerHTML = `
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="badge badge-primary badge-soft">${escapeHtml(groupLabel)}</div>
                            <h4 class="text-lg font-semibold text-base-content">{{ __('Definisci chi rientra in questo gruppo') }}</h4>
                            <p class="text-sm text-base-content/70">
                                {{ __('Puoi scegliere piu ruoli, piu mansioni oppure entrambi. Se compili entrambe le sezioni, l utente deve avere almeno un ruolo selezionato e almeno una mansione selezionata.') }}
                            </p>
                        </div>
                        <button type="button" class="btn btn-error btn-outline btn-sm" data-remove-group="${groupIndex}">
                            <x-lucide-trash-2 class="h-4 w-4" />
                            <span>{{ __('Rimuovi gruppo') }}</span>
                        </button>
                    </div>

                    <div class="mt-5 grid gap-4 xl:grid-cols-2">
                        <div class="rounded-2xl border border-base-300 bg-base-200/30 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h5 class="font-semibold text-base-content">{{ __('Ruoli compresi in questo gruppo') }}</h5>
                                    <p class="mt-1 text-sm text-base-content/70">{{ __('Seleziona uno o piu ruoli validi per questo requisito.') }}</p>
                                </div>
                                <button type="button" class="btn btn-primary btn-outline btn-sm" data-open-selector data-group-index="${groupIndex}" data-type="role">
                                    <x-lucide-briefcase-business class="h-4 w-4" />
                                    <span>{{ __('Seleziona ruoli') }}</span>
                                </button>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2" data-selected-badges data-group-index="${groupIndex}" data-type="role">
                                ${createSelectedBadges('role', groupIndex, '{{ __('Nessun ruolo selezionato.') }}')}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-base-300 bg-base-200/30 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h5 class="font-semibold text-base-content">{{ __('Mansioni comprese in questo gruppo') }}</h5>
                                    <p class="mt-1 text-sm text-base-content/70">{{ __('Apri il modal per cercare rapidamente le mansioni e aggiungerle al gruppo.') }}</p>
                                </div>
                                <button type="button" class="btn btn-primary btn-outline btn-sm" data-open-selector data-group-index="${groupIndex}" data-type="task">
                                    <x-lucide-list-checks class="h-4 w-4" />
                                    <span>{{ __('Seleziona mansioni') }}</span>
                                </button>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2" data-selected-badges data-group-index="${groupIndex}" data-type="task">
                                ${createSelectedBadges('task', groupIndex, '{{ __('Nessuna mansione selezionata.') }}')}
                            </div>
                        </div>
                    </div>
                `;

                return section;
            }

            function renderSummary() {
                const cards = state.map((group, groupIndex) => {
                    const roleItems = getSelectedItems('role', groupIndex);
                    const taskItems = getSelectedItems('task', groupIndex);
                    const rows = [];

                    if (roleItems.length > 0) {
                        rows.push(`
                            <p class="text-sm text-base-content/80">
                                <span class="font-semibold">{{ __('Ruolo compreso tra questi:') }}</span>
                                ${escapeHtml(roleItems.map((item) => item.name).join(', '))}
                            </p>
                        `);
                    }

                    if (taskItems.length > 0) {
                        rows.push(`
                            <p class="text-sm text-base-content/80">
                                <span class="font-semibold">{{ __('Mansione compresa tra queste:') }}</span>
                                ${escapeHtml(taskItems.map((item) => item.name).join(', '))}
                            </p>
                        `);
                    }

                    const title = groupIndex === 0
                        ? '{{ __('Questo requisito si applica a chi rientra in questo gruppo') }}'
                        : '{{ __('Oppure in questo gruppo') }}';

                    return `
                        <div class="rounded-2xl border border-primary/15 bg-base-100 p-4">
                            <h4 class="font-semibold text-base-content">${escapeHtml(title)}</h4>
                            <div class="mt-3 space-y-2">
                                ${rows.length > 0
                                    ? rows.join('')
                                    : `<p class="text-sm text-base-content/60">{{ __('Completa almeno un ruolo o una mansione per descrivere questo gruppo.') }}</p>`}
                            </div>
                        </div>
                    `;
                });

                summaryRoot.innerHTML = `
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-lg font-semibold text-base-content">{{ __('Recap finale') }}</h3>
                            <p class="text-sm text-base-content/70">{{ __('Controlla la frase finale per verificare rapidamente la logica del requisito prima del salvataggio.') }}</p>
                        </div>
                        <div class="space-y-3">
                            ${cards.join('')}
                        </div>
                    </div>
                `;
            }

            function renderSelectorResults() {
                if (!selectorContext || !selectorResults || !selectorSearch) {
                    return;
                }

                const { groupIndex, type } = selectorContext;
                const query = selectorSearch.value.trim().toLocaleLowerCase('it');
                const selectedIds = new Set(type === 'role' ? state[groupIndex].roleIds : state[groupIndex].taskIds);

                const rows = dictionaries[type]
                    .filter((item) => item.name.toLocaleLowerCase('it').includes(query))
                    .sort((left, right) => left.name.localeCompare(right.name, 'it', { sensitivity: 'base' }))
                    .map((item) => {
                        const isSelected = selectedIds.has(Number(item.id));

                        return `
                            <tr>
                                <td>${escapeHtml(item.name)}</td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="btn btn-sm ${isSelected ? 'btn-success btn-disabled' : 'btn-primary btn-outline'}"
                                        data-selector-add
                                        data-id="${item.id}"
                                        ${isSelected ? 'disabled' : ''}
                                    >
                                        ${isSelected ? '{{ __('Aggiunta') }}' : '{{ __('Aggiungi') }}'}
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                selectorResults.innerHTML = rows.length > 0
                    ? rows.join('')
                    : `<tr><td colspan="2" class="py-8 text-center text-sm text-base-content/60">{{ __('Nessun risultato trovato.') }}</td></tr>`;
            }

            function renderSelectorSelected() {
                if (!selectorContext || !selectorSelectedBadges || !selectorSelectedList || !selectorSelectedTitle || !selectorSelectedDescription) {
                    return;
                }

                const { groupIndex, type } = selectorContext;
                const items = getSelectedItems(type, groupIndex);
                const singularLabel = type === 'role' ? '{{ __('ruolo') }}' : '{{ __('mansione') }}';
                const pluralLabel = type === 'role' ? '{{ __('ruoli') }}' : '{{ __('mansioni') }}';

                selectorSelectedTitle.textContent = type === 'role'
                    ? '{{ __('Ruoli selezionati') }}'
                    : '{{ __('Mansioni selezionate') }}';

                selectorSelectedDescription.textContent = items.length > 0
                    ? `${items.length} ${items.length === 1 ? singularLabel : pluralLabel}`
                    : (type === 'role'
                        ? '{{ __('Nessun ruolo selezionato in questo gruppo.') }}'
                        : '{{ __('Nessuna mansione selezionata in questo gruppo.') }}');

                selectorSelectedBadges.innerHTML = items.length > 0
                    ? items.map((item) => `
                        <span class="badge badge-primary badge-soft gap-2 px-3 py-3">
                            <span>${escapeHtml(item.name)}</span>
                            <button
                                type="button"
                                class="rounded-full text-primary-content/80 transition hover:text-base-content"
                                data-selector-remove
                                data-id="${item.id}"
                                aria-label="${escapeHtml(type === 'role' ? '{{ __('Rimuovi ruolo') }}' : '{{ __('Rimuovi mansione') }}')}"
                            >
                                &times;
                            </button>
                        </span>
                    `).join('')
                    : '';

                selectorSelectedList.innerHTML = items.length > 0
                    ? items.map((item) => `
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                            <span class="text-sm text-base-content">${escapeHtml(item.name)}</span>
                            <button type="button" class="btn btn-ghost btn-xs text-error" data-selector-remove data-id="${item.id}">
                                {{ __('Rimuovi') }}
                            </button>
                        </div>
                    `).join('')
                    : `<p class="text-sm text-base-content/60">{{ __('Usa la tabella a sinistra per aggiungere elementi al gruppo.') }}</p>`;
            }

            function openSelector(groupIndex, type) {
                if (!selectorModal || !selectorTitle || !selectorDescription || !selectorSearch) {
                    return;
                }

                selectorContext = { groupIndex, type };
                selectorTitle.textContent = type === 'role'
                    ? '{{ __('Seleziona i ruoli del gruppo') }}'
                    : '{{ __('Seleziona le mansioni del gruppo') }}';
                selectorDescription.textContent = type === 'role'
                    ? '{{ __('Cerca i ruoli e aggiungili al gruppo con il pulsante sulla riga.') }}'
                    : '{{ __('Cerca le mansioni in ordine alfabetico e aggiungile al gruppo con il pulsante sulla riga.') }}';
                selectorSearch.value = '';
                renderSelectorResults();
                renderSelectorSelected();
                selectorModal.showModal();
            }

            function closeSelector() {
                selectorContext = null;

                if (selectorModal?.open) {
                    selectorModal.close();
                }
            }

            function updateSelection(type, groupIndex, itemId, shouldAdd) {
                const key = type === 'role' ? 'roleIds' : 'taskIds';
                const currentIds = new Set(state[groupIndex][key]);

                if (shouldAdd) {
                    currentIds.add(Number(itemId));
                } else {
                    currentIds.delete(Number(itemId));
                }

                state[groupIndex][key] = getSortedIds(type, [...currentIds]);
                render();

                if (selectorContext && selectorContext.groupIndex === groupIndex && selectorContext.type === type) {
                    renderSelectorResults();
                    renderSelectorSelected();
                }
            }

            function bindEvents() {
                groupsRoot.querySelectorAll('[data-open-selector]').forEach((button) => {
                    button.addEventListener('click', () => {
                        openSelector(Number(button.dataset.groupIndex), button.dataset.type);
                    });
                });

                groupsRoot.querySelectorAll('[data-remove-group]').forEach((button) => {
                    button.addEventListener('click', () => {
                        state.splice(Number(button.dataset.removeGroup), 1);

                        if (state.length === 0) {
                            state = [{ roleIds: [], taskIds: [] }];
                        }

                        render();
                    });
                });

                groupsRoot.querySelectorAll('[data-remove-selected]').forEach((button) => {
                    button.addEventListener('click', () => {
                        updateSelection(
                            button.dataset.type,
                            Number(button.dataset.groupIndex),
                            Number(button.dataset.id),
                            false,
                        );
                    });
                });
            }

            function render() {
                groupsRoot.replaceChildren();

                state.forEach((group, groupIndex) => {
                    groupsRoot.appendChild(renderGroup(group, groupIndex));
                });

                addGroupButton.disabled = state.length >= maxGroups;
                renderSummary();
                syncRulesJson();
                bindEvents();
            }

            addGroupButton.addEventListener('click', () => {
                if (state.length >= maxGroups) {
                    return;
                }

                state.push({ roleIds: [], taskIds: [] });
                render();
            });

            selectorSearch?.addEventListener('input', renderSelectorResults);

            selectorResults?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-selector-add]');

                if (!button || !selectorContext) {
                    return;
                }

                updateSelection(selectorContext.type, selectorContext.groupIndex, Number(button.dataset.id), true);
            });

            selectorSelectedBadges?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-selector-remove]');

                if (!button || !selectorContext) {
                    return;
                }

                updateSelection(selectorContext.type, selectorContext.groupIndex, Number(button.dataset.id), false);
            });

            selectorSelectedList?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-selector-remove]');

                if (!button || !selectorContext) {
                    return;
                }

                updateSelection(selectorContext.type, selectorContext.groupIndex, Number(button.dataset.id), false);
            });

            selectorClose?.addEventListener('click', closeSelector);
            selectorDone?.addEventListener('click', closeSelector);
            selectorBackdrop?.addEventListener('click', closeSelector);
            selectorModal?.addEventListener('close', () => {
                selectorContext = null;
            });

            form.addEventListener('submit', syncRulesJson);
            render();
        });
    </script>
@endpush
