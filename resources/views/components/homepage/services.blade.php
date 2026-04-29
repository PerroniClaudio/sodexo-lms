@props([
    'label' => null,
    'leftContentHtml' => null,
    'visualImageUrl' => null,
    'overlayContentHtml' => null,
    'buttonEnabled' => true,
    'buttonColor' => 'primary',
    'buttonText' => 'Esplora il nostro catalogo',
    'buttonUrl' => '#catalogo',
])

@php
    $resolvedLabel = filled($label) ? $label : 'Servizi';
    $resolvedLeftContentHtml = filled($leftContentHtml)
        ? $leftContentHtml
        : '<h2>I nostri <strong>servizi</strong> comprendono</h2><ul><li>Corsi <strong>FAD</strong></li><li>Corsi <strong>RES</strong></li><li>Corsi <strong>FSC</strong></li></ul>';
    $buttonClasses = [
        'primary' => 'bg-primary text-primary-content hover:bg-primary/90',
        'secondary' => 'bg-secondary text-secondary-content hover:bg-secondary/90',
        'accent' => 'bg-accent text-accent-content hover:bg-accent/90',
        'neutral' => 'bg-neutral text-neutral-content hover:bg-neutral/90',
    ];
@endphp

<section id="servizi" class="rounded-box bg-base-100 px-6 py-16 shadow-sm sm:px-12 lg:px-12">
    <div class="grid items-center gap-12 lg:grid-cols-[0.8fr_1.2fr]">
        <div>
            @if (filled($resolvedLabel))
                <p class="text-sm font-semibold text-secondary">{{ $resolvedLabel }}</p>
            @endif

            <div class="mt-3 space-y-5 text-base-content [&_a]:font-semibold [&_a]:text-secondary [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-secondary/40 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-base-300 [&_code]:px-1.5 [&_code]:py-0.5 [&_h1]:text-4xl [&_h1]:font-extrabold [&_h1]:leading-tight [&_h2]:text-4xl [&_h2]:font-extrabold [&_h2]:leading-tight [&_h3]:text-2xl [&_h3]:font-bold [&_li]:text-xl [&_li]:font-bold [&_ol]:space-y-4 [&_ol]:pl-6 [&_p]:text-lg [&_p]:leading-relaxed [&_pre]:overflow-x-auto [&_pre]:rounded-box [&_pre]:bg-base-300 [&_pre]:p-4 [&_strong]:text-secondary [&_ul]:space-y-4 [&_ul]:pl-0 [&_ul]:list-none">
                {!! $resolvedLeftContentHtml !!}
            </div>

            @if ($buttonEnabled)
                <a href="{{ $buttonUrl }}" class="mt-6 inline-flex rounded px-4 py-4 text-xl shadow-md transition {{ $buttonClasses[$buttonColor] ?? $buttonClasses['primary'] }}">
                    {{ $buttonText }}
                </a>
            @endif
        </div>

        <div class="relative min-h-90">
            <div class="absolute right-0 top-0 h-72 w-[86%] bg-base-300"></div>
            <div class="relative mt-8 mr-8 overflow-hidden bg-base-100 shadow-2xl">
                @if ($visualImageUrl)
                    <div class="relative aspect-video bg-base-200">
                        <img src="{{ $visualImageUrl }}" alt="Immagine servizi homepage" class="absolute inset-0 h-full w-full object-cover">
                        <div class="absolute inset-0 bg-linear-to-r from-base-100/80 via-base-100/15 to-secondary/25"></div>
                    </div>
                @else
                    <div class="aspect-video bg-secondary"></div>
                @endif

                @if (filled($overlayContentHtml))
                    <div class="absolute bottom-0 right-0 z-10 w-full max-w-xs rounded-tl-[2rem] bg-primary p-5 text-primary-content shadow-xl sm:right-6 sm:bottom-6">
                        <div class="space-y-3 text-sm [&_a]:font-semibold [&_a]:text-primary-content [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-primary-content/40 [&_blockquote]:pl-3 [&_code]:rounded [&_code]:bg-primary-content/15 [&_code]:px-1.5 [&_code]:py-0.5 [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_h3]:text-lg [&_h3]:font-semibold [&_ol]:space-y-2 [&_ol]:pl-5 [&_p]:leading-relaxed [&_pre]:overflow-x-auto [&_pre]:rounded-box [&_pre]:bg-primary-content/10 [&_pre]:p-3 [&_ul]:space-y-2 [&_ul]:pl-5">
                            {!! $overlayContentHtml !!}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
