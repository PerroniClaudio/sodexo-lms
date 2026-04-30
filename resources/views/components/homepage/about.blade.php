@props([
    'contentHtml' => null,
    'visualImageUrl' => null,
    'buttonEnabled' => true,
    'buttonColor' => 'primary',
    'buttonText' => 'Pulsante contenuti',
    'buttonUrl' => '#servizi',
])

@php
    $resolvedContentHtml = filled($contentHtml)
        ? $contentHtml
        : '<p><strong>Sezione contenuti</strong></p><h2>' . e(config('app.name', 'Laravel')) . '<br>presenta <strong>testo<br>generico</strong></h2><p>' . e(config('app.name', 'Laravel')) . ' mostra qui un contenuto descrittivo generico per homepage.</p>';
    $buttonClasses = [
        'primary' => 'bg-primary text-primary-content hover:bg-primary/90',
        'secondary' => 'bg-secondary text-secondary-content hover:bg-secondary/90',
        'accent' => 'bg-accent text-accent-content hover:bg-accent/90',
        'neutral' => 'bg-neutral text-neutral-content hover:bg-neutral/90',
    ];
@endphp

<section id="catalogo" class="rounded-box bg-base-100 px-6 py-16 shadow-sm sm:px-12 lg:px-12">
    <div class="grid items-center gap-12 lg:grid-cols-2">
        <div class="relative min-h-90">
            <div class="absolute right-0 top-0 h-52 w-[86%] bg-base-300"></div>
            <div class="relative mt-8 mr-8 overflow-hidden bg-base-100 shadow-2xl">
                @if ($visualImageUrl)
                    <div class="relative aspect-video bg-base-200">
                        <img src="{{ $visualImageUrl }}" alt="Immagine sezione chi siamo" class="absolute inset-0 h-full w-full object-cover">
                        <div class="absolute inset-0 bg-linear-to-tr from-base-100/25 via-transparent to-secondary/25"></div>
                    </div>
                @else
                    <div class="aspect-video bg-secondary"></div>
                @endif
            </div>
        </div>

        <div>
            <div class="space-y-5 text-base-content [&_a]:font-semibold [&_a]:text-secondary [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-secondary/40 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-base-300 [&_code]:px-1.5 [&_code]:py-0.5 [&_h1]:text-4xl [&_h1]:font-extrabold [&_h1]:leading-tight [&_h2]:text-4xl [&_h2]:font-extrabold [&_h2]:leading-tight [&_h3]:text-2xl [&_h3]:font-bold [&_li]:text-lg [&_li]:leading-relaxed [&_ol]:space-y-3 [&_ol]:pl-6 [&_p]:text-base [&_p]:font-bold [&_p]:leading-relaxed [&_pre]:overflow-x-auto [&_pre]:rounded-box [&_pre]:bg-base-300 [&_pre]:p-4 [&_strong]:text-secondary [&_ul]:space-y-3 [&_ul]:pl-6">
                {!! $resolvedContentHtml !!}
            </div>

            @if ($buttonEnabled)
                <a href="{{ $buttonUrl }}" class="mt-6 inline-flex rounded px-4 py-4 text-xl shadow-md transition {{ $buttonClasses[$buttonColor] ?? $buttonClasses['primary'] }}">
                    {{ $buttonText }}
                </a>
            @endif
        </div>
    </div>
</section>
