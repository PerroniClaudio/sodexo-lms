<x-layouts.app>
    @php
        $navbarLogoPath = \App\Models\HomepageSetting::value('navbar_logo_path');
        $navbarLogoUrl = $navbarLogoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($navbarLogoPath) : null;
    @endphp

    <div class="min-h-screen bg-base-200 text-base-content">
        <x-homepage.navbar :logo-url="$navbarLogoUrl" />

        <main class="px-4 py-10 sm:px-6 lg:px-0">
            <section class="mx-auto w-full max-w-4xl rounded-box bg-base-100 px-6 py-8 shadow-sm sm:px-8">
                <h1 class="text-3xl font-extrabold sm:text-4xl">{{ $title }}</h1>

                <div class="mt-8 space-y-5 text-base-content [&_a]:font-semibold [&_a]:text-secondary [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-secondary/40 [&_blockquote]:pl-4 [&_code]:rounded [&_code]:bg-base-300 [&_code]:px-1.5 [&_code]:py-0.5 [&_h1]:text-3xl [&_h1]:font-extrabold [&_h1]:leading-tight [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:leading-tight [&_h3]:text-xl [&_h3]:font-bold [&_li]:leading-relaxed [&_ol]:space-y-3 [&_ol]:pl-6 [&_p]:leading-relaxed [&_pre]:overflow-x-auto [&_pre]:rounded-box [&_pre]:bg-base-300 [&_pre]:p-4 [&_strong]:font-bold [&_ul]:space-y-3 [&_ul]:pl-6">
                    {!! $contentHtml !!}
                </div>
            </section>
        </main>

        <x-homepage.footer />
    </div>
</x-layouts.app>
