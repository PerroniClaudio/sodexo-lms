<x-layouts.admin>
    <div class="p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="$title"
            :subtitle="$description"
        />

        <div class="mt-6 rounded-box border border-base-300 bg-base-100 shadow-sm">
            <form method="POST" action="{{ $formAction }}" class="grid gap-6 p-6">
                @csrf

                <div>
                    <label for="{{ $fieldId }}" class="mb-2 block text-sm font-semibold">
                        {{ $title }}
                    </label>
                    <input id="{{ $fieldId }}" type="hidden" name="content_html" value="{{ old('content_html', $contentHtml) }}">
                    <div class="flex flex-wrap gap-2 rounded-t-box border border-b-0 border-base-300 bg-base-200 p-2" data-tiptap-toolbar="{{ $fieldId }}">
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="undo" aria-label="{{ __('Annulla') }}" title="{{ __('Annulla') }}"><x-lucide-undo-2 class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="redo" aria-label="{{ __('Ripeti') }}" title="{{ __('Ripeti') }}"><x-lucide-redo-2 class="h-4 w-4" /></button>
                        <span class="mx-1 h-6 w-px bg-base-300"></span>
                        <button type="button" class="btn btn-xs btn-ghost" data-command="heading" data-level="1" aria-label="{{ __('Titolo grande') }}" title="{{ __('Titolo grande') }}"><x-lucide-heading-1 class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost" data-command="heading" data-level="2" aria-label="{{ __('Titolo medio') }}" title="{{ __('Titolo medio') }}"><x-lucide-heading-2 class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost" data-command="heading" data-level="3" aria-label="{{ __('Titolo piccolo') }}" title="{{ __('Titolo piccolo') }}"><x-lucide-heading-3 class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="paragraph" aria-label="{{ __('Paragrafo') }}" title="{{ __('Paragrafo') }}"><x-lucide-pilcrow class="h-4 w-4" /></button>
                        <span class="mx-1 h-6 w-px bg-base-300"></span>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="bold" aria-label="{{ __('Grassetto') }}" title="{{ __('Grassetto') }}"><x-lucide-bold class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="italic" aria-label="{{ __('Corsivo') }}" title="{{ __('Corsivo') }}"><x-lucide-italic class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="underline" aria-label="{{ __('Sottolineato') }}" title="{{ __('Sottolineato') }}"><x-lucide-underline class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="link" aria-label="{{ __('Link') }}" title="{{ __('Link') }}"><x-lucide-link class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="unsetLink" aria-label="{{ __('Rimuovi link') }}" title="{{ __('Rimuovi link') }}"><x-lucide-unlink class="h-4 w-4" /></button>
                        <span class="mx-1 h-6 w-px bg-base-300"></span>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="bulletList" aria-label="{{ __('Elenco puntato') }}" title="{{ __('Elenco puntato') }}"><x-lucide-list class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="orderedList" aria-label="{{ __('Elenco numerato') }}" title="{{ __('Elenco numerato') }}"><x-lucide-list-ordered class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="blockquote" aria-label="{{ __('Citazione') }}" title="{{ __('Citazione') }}"><x-lucide-quote class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="codeBlock" aria-label="{{ __('Blocco codice') }}" title="{{ __('Blocco codice') }}"><x-lucide-code class="h-4 w-4" /></button>
                        <span class="mx-1 h-6 w-px bg-base-300"></span>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="align" data-align="left" aria-label="{{ __('Allinea a sinistra') }}" title="{{ __('Allinea a sinistra') }}"><x-lucide-align-left class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="align" data-align="center" aria-label="{{ __('Allinea al centro') }}" title="{{ __('Allinea al centro') }}"><x-lucide-align-center class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="align" data-align="right" aria-label="{{ __('Allinea a destra') }}" title="{{ __('Allinea a destra') }}"><x-lucide-align-right class="h-4 w-4" /></button>
                        <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="horizontalRule" aria-label="{{ __('Separatore') }}" title="{{ __('Separatore') }}"><x-lucide-minus class="h-4 w-4" /></button>
                    </div>
                    <div data-tiptap-editor data-target="{{ $fieldId }}" class="min-h-96 rounded-b-box border border-base-300 bg-base-100 p-4 focus-within:border-primary"></div>
                    @error('content_html')
                        <p class="mt-2 text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                </div>
            </form>
        </div>
    </div>

    @vite('resources/js/pages/admin-homepage-edit.js')
</x-layouts.admin>
