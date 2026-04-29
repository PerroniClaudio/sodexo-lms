<x-layouts.admin>
    <div class="p-4 sm:p-6 lg:p-8">
        @php
            $homepageButtonColors = [
                'primary' => ['label' => __('Primario'), 'swatch' => 'bg-primary'],
                'secondary' => ['label' => __('Secondario'), 'swatch' => 'bg-secondary'],
                'accent' => ['label' => __('Accento'), 'swatch' => 'bg-accent'],
                'neutral' => ['label' => __('Neutro'), 'swatch' => 'bg-neutral'],
            ];
        @endphp

        <x-page-header
            :title="__('Home page')"
            :subtitle="__('Personalizzazione contenuti della pagina pubblica.')"
        />

        <div class="collapse collapse-arrow mt-6 border border-base-300 bg-base-100 shadow-sm" data-homepage-card="navigation">
            <input type="checkbox" data-homepage-card-toggle="navigation">
            <div class="collapse-title min-h-0 p-0">
                <div class="flex flex-col gap-6 p-6 pr-14 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="card-title">{{ __('Barra di navigazione') }}</h2>
                        <p class="mt-1 text-sm text-base-content/70">
                            {{ __('Carica il logo mostrato in alto a sinistra nella homepage pubblica.') }}
                        </p>
                    </div>

                    <div class="flex self-start lg:self-center">
                        <div class="flex min-h-20 min-w-48 items-center justify-center rounded-box border border-base-300 bg-base-200 p-4">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ __('Logo barra di navigazione') }}" class="max-h-16 max-w-48 object-contain">
                            @else
                                <x-homepage.logo />
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="collapse-content px-6 pb-6">
                <form method="POST" action="{{ route('admin.homepage.navigation.update') }}" enctype="multipart/form-data" class="grid gap-4 border-t border-base-300 pt-6 lg:grid-cols-[1fr_auto] lg:items-end">
                    @csrf

                    <label class="form-control w-full">
                        <span class="label">
                            <span class="label-text font-semibold">{{ __('Logo') }}</span>
                        </span>
                        <input
                            type="file"
                            name="logo"
                            accept="image/png,image/jpeg,image/webp,image/svg+xml"
                            class="file-input file-input-bordered w-full @error('logo') file-input-error @enderror"
                            required
                        >
                        @error('logo')
                            <span class="label">
                                <span class="label-text-alt text-error">{{ $message }}</span>
                            </span>
                        @enderror
                    </label>

                    <button type="submit" class="btn btn-primary">
                        {{ __('Salva logo') }}
                    </button>
                </form>
            </div>
        </div>

        <div class="collapse collapse-arrow mt-6 border border-base-300 bg-base-100 shadow-sm" data-homepage-card="hero">
            <input type="checkbox" data-homepage-card-toggle="hero">
            <div class="collapse-title min-h-0 p-0">
                <div class="flex items-start justify-between gap-4 p-6 pr-14">
                    <div>
                        <h2 class="card-title">{{ __('Hero') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Personalizza sfondo, testo centrale e bottone della sezione iniziale.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="collapse-content px-6 pb-6">
                <form method="POST" action="{{ route('admin.homepage.hero.update') }}" enctype="multipart/form-data" class="grid gap-6 border-t border-base-300 pt-6">
                    @csrf

                    <div class="grid gap-5">
                            <label class="form-control w-full">
                                <span class="label">
                                    <span class="label-text font-semibold">{{ __('Immagine di sfondo') }}</span>
                                </span>
                                <input
                                    type="file"
                                    name="background_image"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="file-input file-input-bordered w-full @error('background_image') file-input-error @enderror"
                                >
                                <span class="label">
                                    <span class="label-text-alt">{{ __('Se non carichi un\'immagine, verrà utilizzato un colore di default.') }}</span>
                                </span>
                                @error('background_image')
                                    <span class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </span>
                                @enderror
                            </label>

                            <div>
                                <label for="hero-content" class="mb-2 block text-sm font-semibold">
                                    {{ __('Testo centrale') }}
                                </label>
                                <input id="hero-content" type="hidden" name="content" value="{{ old('content', $heroContent) }}">
                                <div class="flex flex-wrap gap-2 rounded-t-box border border-b-0 border-base-300 bg-base-200 p-2" data-tiptap-toolbar="hero-content">
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="undo" aria-label="{{ __('Annulla') }}" title="{{ __('Annulla') }}">
                                        <x-lucide-undo-2 class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="redo" aria-label="{{ __('Ripeti') }}" title="{{ __('Ripeti') }}">
                                        <x-lucide-redo-2 class="h-4 w-4" />
                                    </button>
                                    <span class="mx-1 h-6 w-px bg-base-300"></span>
                                    <button type="button" class="btn btn-xs btn-ghost" data-command="heading" data-level="1" aria-label="{{ __('Titolo grande') }}" title="{{ __('Titolo grande') }}">
                                        <x-lucide-heading-1 class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost" data-command="heading" data-level="2" aria-label="{{ __('Titolo medio') }}" title="{{ __('Titolo medio') }}">
                                        <x-lucide-heading-2 class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost" data-command="heading" data-level="3" aria-label="{{ __('Titolo piccolo') }}" title="{{ __('Titolo piccolo') }}">
                                        <x-lucide-heading-3 class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="paragraph" aria-label="{{ __('Paragrafo') }}" title="{{ __('Paragrafo') }}">
                                        <x-lucide-pilcrow class="h-4 w-4" />
                                    </button>
                                    <span class="mx-1 h-6 w-px bg-base-300"></span>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="bold" aria-label="{{ __('Grassetto') }}" title="{{ __('Grassetto') }}">
                                        <x-lucide-bold class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="italic" aria-label="{{ __('Corsivo') }}" title="{{ __('Corsivo') }}">
                                        <x-lucide-italic class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="underline" aria-label="{{ __('Sottolineato') }}" title="{{ __('Sottolineato') }}">
                                        <x-lucide-underline class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="link" aria-label="{{ __('Link') }}" title="{{ __('Link') }}">
                                        <x-lucide-link class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="unsetLink" aria-label="{{ __('Rimuovi link') }}" title="{{ __('Rimuovi link') }}">
                                        <x-lucide-unlink class="h-4 w-4" />
                                    </button>
                                    <span class="mx-1 h-6 w-px bg-base-300"></span>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="bulletList" aria-label="{{ __('Elenco puntato') }}" title="{{ __('Elenco puntato') }}">
                                        <x-lucide-list class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="orderedList" aria-label="{{ __('Elenco numerato') }}" title="{{ __('Elenco numerato') }}">
                                        <x-lucide-list-ordered class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="blockquote" aria-label="{{ __('Citazione') }}" title="{{ __('Citazione') }}">
                                        <x-lucide-quote class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="codeBlock" aria-label="{{ __('Blocco codice') }}" title="{{ __('Blocco codice') }}">
                                        <x-lucide-code class="h-4 w-4" />
                                    </button>
                                    <span class="mx-1 h-6 w-px bg-base-300"></span>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="align" data-align="left" aria-label="{{ __('Allinea a sinistra') }}" title="{{ __('Allinea a sinistra') }}">
                                        <x-lucide-align-left class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="align" data-align="center" aria-label="{{ __('Allinea al centro') }}" title="{{ __('Allinea al centro') }}">
                                        <x-lucide-align-center class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="align" data-align="right" aria-label="{{ __('Allinea a destra') }}" title="{{ __('Allinea a destra') }}">
                                        <x-lucide-align-right class="h-4 w-4" />
                                    </button>
                                    <button type="button" class="btn btn-xs btn-ghost btn-square" data-command="horizontalRule" aria-label="{{ __('Separatore') }}" title="{{ __('Separatore') }}">
                                        <x-lucide-minus class="h-4 w-4" />
                                    </button>
                                </div>
                                <div
                                    data-tiptap-editor
                                    data-target="hero-content"
                                    class="min-h-52 rounded-b-box border border-base-300 bg-base-100 p-4 focus-within:border-primary"
                                ></div>
                                @error('content')
                                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                    </div>

                    <div class="rounded-box border border-base-300 p-4">
                        <label class="label cursor-pointer justify-start gap-3 p-0">
                            <input type="hidden" name="button_enabled" value="0">
                            <input type="checkbox" name="button_enabled" value="1" class="toggle toggle-primary" @checked(old('button_enabled', $heroButtonEnabled))>
                            <span class="label-text font-semibold">{{ __('Mostra bottone hero') }}</span>
                        </label>

                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            <div class="form-control md:col-span-3">
                                <span class="label">
                                    <span class="label-text font-semibold">{{ __('Colore bottone') }}</span>
                                </span>
                                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                    @foreach ($homepageButtonColors as $value => $color)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-box border border-base-300 px-4 py-3 transition hover:border-base-content/30 has-checked:border-primary has-checked:bg-base-200/70">
                                            <input
                                                type="radio"
                                                name="button_color"
                                                value="{{ $value }}"
                                                class="radio radio-primary radio-sm"
                                                @checked(old('button_color', $heroButtonColor) === $value)
                                            >
                                            <span class="inline-block size-4 rounded-full border border-base-300 {{ $color['swatch'] }}"></span>
                                            <span class="text-sm font-medium">{{ $color['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('button_color')
                                    <span class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </span>
                                @enderror
                            </div>

                            <label class="form-control">
                                <span class="label">
                                    <span class="label-text font-semibold">{{ __('Testo bottone') }}</span>
                                </span>
                                <input type="text" name="button_text" value="{{ old('button_text', $heroButtonText) }}" class="input input-bordered @error('button_text') input-error @enderror">
                                @error('button_text')
                                    <span class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </span>
                                @enderror
                            </label>

                            <label class="form-control">
                                <span class="label">
                                    <span class="label-text font-semibold">{{ __('Link bottone') }}</span>
                                </span>
                                <input type="text" name="button_url" value="{{ old('button_url', $heroButtonUrl) }}" class="input input-bordered @error('button_url') input-error @enderror">
                                @error('button_url')
                                    <span class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </span>
                                @enderror
                            </label>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">
                            {{ __('Salva hero') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="collapse collapse-arrow mt-6 border border-base-300 bg-base-100 shadow-sm" data-homepage-card="services">
            <input type="checkbox" data-homepage-card-toggle="services">
            <div class="collapse-title min-h-0 p-0">
                <div class="flex items-start justify-between gap-4 p-6 pr-14">
                    <div>
                        <h2 class="card-title">{{ __('Servizi') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Gestisci il contenuto libero delle due aree, l\'immagine principale e la CTA della sezione servizi.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="collapse-content px-6 pb-6">
                <form method="POST" action="{{ route('admin.homepage.services.update') }}" enctype="multipart/form-data" class="grid gap-6 border-t border-base-300 pt-6">
                    @csrf

                    <div class="grid gap-5">
                        <label class="form-control w-full">
                            <span class="label">
                                <span class="label-text font-semibold">{{ __('Label sezione') }}</span>
                            </span>
                            <input type="text" name="label" value="{{ old('label', $servicesLabel) }}" class="input input-bordered w-full @error('label') input-error @enderror">
                            @error('label')
                                <span class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </span>
                            @enderror
                        </label>

                        <label class="form-control w-full">
                            <span class="label">
                                <span class="label-text font-semibold">{{ __('Immagine principale destra') }}</span>
                            </span>
                            <input type="file" name="visual_image" accept="image/png,image/jpeg,image/webp" class="file-input file-input-bordered w-full @error('visual_image') file-input-error @enderror">
                            <span class="label">
                                <span class="label-text-alt">{{ __('Se non carichi un\'immagine, verrà utilizzato un colore di default.') }}</span>
                            </span>
                            @error('visual_image')
                                <span class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </span>
                            @enderror
                        </label>

                        <div>
                            <label for="services-left-content" class="mb-2 block text-sm font-semibold">
                                {{ __('Contenuto colonna sinistra') }}
                            </label>
                            <input id="services-left-content" type="hidden" name="left_content_html" value="{{ old('left_content_html', $servicesLeftContentHtml) }}">
                            <div class="flex flex-wrap gap-2 rounded-t-box border border-b-0 border-base-300 bg-base-200 p-2" data-tiptap-toolbar="services-left-content">
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
                            <div data-tiptap-editor data-target="services-left-content" class="min-h-52 rounded-b-box border border-base-300 bg-base-100 p-4 focus-within:border-primary"></div>
                            @error('left_content_html')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="services-overlay-content" class="mb-2 block text-sm font-semibold">
                                {{ __('Contenuto overlay destro') }}
                            </label>
                            <input id="services-overlay-content" type="hidden" name="overlay_content_html" value="{{ old('overlay_content_html', $servicesOverlayContentHtml) }}">
                            <div class="flex flex-wrap gap-2 rounded-t-box border border-b-0 border-base-300 bg-base-200 p-2" data-tiptap-toolbar="services-overlay-content">
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
                            <div data-tiptap-editor data-target="services-overlay-content" class="min-h-52 rounded-b-box border border-base-300 bg-base-100 p-4 focus-within:border-primary"></div>
                            <p class="mt-2 text-xs text-base-content/60">{{ __('L\'overlay viene mostrato solo se questo contenuto non è vuoto.') }}</p>
                            @error('overlay_content_html')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-box border border-base-300 p-4">
                            <label class="label cursor-pointer justify-start gap-3 p-0">
                                <input type="hidden" name="button_enabled" value="0">
                                <input type="checkbox" name="button_enabled" value="1" class="toggle toggle-primary" @checked(old('button_enabled', $servicesButtonEnabled))>
                                <span class="label-text font-semibold">{{ __('Mostra CTA servizi') }}</span>
                            </label>

                            <div class="mt-4 grid gap-4">
                                <div class="form-control w-full">
                                    <label class="label" for="services-button-color">
                                        <span class="label-text font-semibold">{{ __('Colore CTA') }}</span>
                                    </label>
                                    <div id="services-button-color" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        @foreach ($homepageButtonColors as $value => $color)
                                            <label class="flex cursor-pointer items-center gap-3 rounded-box border border-base-300 px-4 py-3 transition hover:border-base-content/30 has-checked:border-primary has-checked:bg-base-200/70">
                                                <input
                                                    type="radio"
                                                    name="button_color"
                                                    value="{{ $value }}"
                                                    class="radio radio-primary radio-sm"
                                                    @checked(old('button_color', $servicesButtonColor) === $value)
                                                >
                                                <span class="inline-block size-4 rounded-full border border-base-300 {{ $color['swatch'] }}"></span>
                                                <span class="text-sm font-medium">{{ $color['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('button_color')
                                        <span class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </span>
                                    @enderror
                                </div>

                                <div class="form-control w-full">
                                    <label class="label" for="services-button-text">
                                        <span class="label-text font-semibold">{{ __('Testo CTA') }}</span>
                                    </label>
                                    <input id="services-button-text" type="text" name="button_text" value="{{ old('button_text', $servicesButtonText) }}" class="input input-bordered w-full @error('button_text') input-error @enderror">
                                    @error('button_text')
                                        <span class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </span>
                                    @enderror
                                </div>

                                <div class="form-control w-full">
                                    <label class="label" for="services-button-url">
                                        <span class="label-text font-semibold">{{ __('Link CTA') }}</span>
                                    </label>
                                    <input id="services-button-url" type="text" name="button_url" value="{{ old('button_url', $servicesButtonUrl) }}" class="input input-bordered w-full @error('button_url') input-error @enderror">
                                    @error('button_url')
                                        <span class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">{{ __('Salva servizi') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="collapse collapse-arrow mt-6 border border-base-300 bg-base-100 shadow-sm" data-homepage-card="about">
            <input type="checkbox" data-homepage-card-toggle="about">
            <div class="collapse-title min-h-0 p-0">
                <div class="p-6 pr-14">
                    <div>
                        <h2 class="card-title">{{ __('Chi siamo') }}</h2>
                        <p class="mt-1 text-sm text-base-content/70">
                            {{ __('Gestisci immagine, testo libero HTML e CTA della sezione Chi siamo.') }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="collapse-content px-6 pb-6">
                <form method="POST" action="{{ route('admin.homepage.about.update') }}" enctype="multipart/form-data" class="grid gap-6 border-t border-base-300 pt-6">
                    @csrf

                    <div class="grid gap-5">
                        <label class="form-control w-full">
                            <span class="label">
                                <span class="label-text font-semibold">{{ __('Immagine principale') }}</span>
                            </span>
                            <input type="file" name="visual_image" accept="image/png,image/jpeg,image/webp" class="file-input file-input-bordered w-full @error('visual_image') file-input-error @enderror">
                            <span class="label">
                                <span class="label-text-alt">{{ __('Se non carichi un\'immagine, verrà mantenuto il layout grafico di default.') }}</span>
                            </span>
                            @error('visual_image')
                                <span class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </span>
                            @enderror
                        </label>

                        <div>
                            <label for="about-content" class="mb-2 block text-sm font-semibold">
                                {{ __('Contenuto testuale') }}
                            </label>
                            <input id="about-content" type="hidden" name="content_html" value="{{ old('content_html', $aboutContentHtml) }}">
                            <div class="flex flex-wrap gap-2 rounded-t-box border border-b-0 border-base-300 bg-base-200 p-2" data-tiptap-toolbar="about-content">
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
                            <div data-tiptap-editor data-target="about-content" class="min-h-52 rounded-b-box border border-base-300 bg-base-100 p-4 focus-within:border-primary"></div>
                            @error('content_html')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="rounded-box border border-base-300 p-4">
                            <label class="label cursor-pointer justify-start gap-3 p-0">
                                <input type="hidden" name="button_enabled" value="0">
                                <input type="checkbox" name="button_enabled" value="1" class="toggle toggle-primary" @checked(old('button_enabled', $aboutButtonEnabled))>
                                <span class="label-text font-semibold">{{ __('Mostra CTA Chi siamo') }}</span>
                            </label>

                            <div class="mt-4 grid gap-4">
                                <div class="form-control w-full">
                                    <label class="label" for="about-button-color">
                                        <span class="label-text font-semibold">{{ __('Colore CTA') }}</span>
                                    </label>
                                    <div id="about-button-color" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        @foreach ($homepageButtonColors as $value => $color)
                                            <label class="flex cursor-pointer items-center gap-3 rounded-box border border-base-300 px-4 py-3 transition hover:border-base-content/30 has-checked:border-primary has-checked:bg-base-200/70">
                                                <input
                                                    type="radio"
                                                    name="button_color"
                                                    value="{{ $value }}"
                                                    class="radio radio-primary radio-sm"
                                                    @checked(old('button_color', $aboutButtonColor) === $value)
                                                >
                                                <span class="inline-block size-4 rounded-full border border-base-300 {{ $color['swatch'] }}"></span>
                                                <span class="text-sm font-medium">{{ $color['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('button_color')
                                        <span class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </span>
                                    @enderror
                                </div>

                                <div class="form-control w-full">
                                    <label class="label" for="about-button-text">
                                        <span class="label-text font-semibold">{{ __('Testo CTA') }}</span>
                                    </label>
                                    <input id="about-button-text" type="text" name="button_text" value="{{ old('button_text', $aboutButtonText) }}" class="input input-bordered w-full @error('button_text') input-error @enderror">
                                    @error('button_text')
                                        <span class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </span>
                                    @enderror
                                </div>

                                <div class="form-control w-full">
                                    <label class="label" for="about-button-url">
                                        <span class="label-text font-semibold">{{ __('Link CTA') }}</span>
                                    </label>
                                    <input id="about-button-url" type="text" name="button_url" value="{{ old('button_url', $aboutButtonUrl) }}" class="input input-bordered w-full @error('button_url') input-error @enderror">
                                    @error('button_url')
                                        <span class="label">
                                            <span class="label-text-alt text-error">{{ $message }}</span>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">{{ __('Salva Chi siamo') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite('resources/js/pages/admin-homepage-edit.js')
</x-layouts.admin>
