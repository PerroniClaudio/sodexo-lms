@props([
    'backgroundImageUrl' => null,
    'contentHtml' => null,
    'buttonEnabled' => true,
    'buttonColor' => 'secondary',
    'buttonText' => 'U20m - Under20Minutes',
    'buttonUrl' => '#servizi',
])

@php
    $contentHtml ??= '<h1>Lavoriamo per la<br><strong>SALUTE</strong></h1><p>Realizzando percorsi formativi<br>di Educazione Continua in Medicina</p>';

    $buttonClasses = [
        'primary' => 'bg-primary text-primary-content hover:bg-primary/90 focus:ring-primary-content',
        'secondary' => 'bg-secondary text-secondary-content hover:bg-secondary/90 focus:ring-secondary-content',
        'accent' => 'bg-accent text-accent-content hover:bg-accent/90 focus:ring-accent-content',
        'neutral' => 'bg-neutral text-neutral-content hover:bg-neutral/90 focus:ring-neutral-content',
    ][$buttonColor] ?? 'bg-secondary text-secondary-content hover:bg-secondary/90 focus:ring-secondary-content';
@endphp

<section
    class="relative isolate flex min-h-[500px] items-center overflow-hidden bg-primary text-primary-content"
    @if ($backgroundImageUrl)
        style="background-image: linear-gradient(rgba(31, 37, 89, .58), rgba(31, 37, 89, .58)), url('{{ $backgroundImageUrl }}'); background-position: center; background-size: cover;"
    @endif
>
    <div class="relative mx-auto w-full max-w-6xl px-4 py-20 text-center sm:px-6 lg:px-0">
        <div class="homepage-hero-content mx-auto max-w-4xl">
            {!! $contentHtml !!}
        </div>

        @if ($buttonEnabled && filled($buttonText) && filled($buttonUrl))
            <a
                href="{{ $buttonUrl }}"
                class="{{ $buttonClasses }} mt-5 inline-flex items-center rounded px-7 py-4 text-lg font-bold shadow-lg transition focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary focus:outline-none"
            >
                {{ $buttonText }}
            </a>
        @endif
    </div>
</section>
