<x-layouts.app>
    @php
        $navbarLogoPath = \App\Models\HomepageSetting::value('navbar_logo_path');
        $navbarLogoUrl = $navbarLogoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($navbarLogoPath) : null;
        $heroBackgroundImagePath = \App\Models\HomepageSetting::value('hero_background_image_path');
        $heroBackgroundImageUrl = $heroBackgroundImagePath ? \Illuminate\Support\Facades\Storage::disk('public')->url($heroBackgroundImagePath) : null;
        $servicesVisualImagePath = \App\Models\HomepageSetting::value('services_visual_image_path');
        $servicesVisualImageUrl = $servicesVisualImagePath ? \Illuminate\Support\Facades\Storage::disk('public')->url($servicesVisualImagePath) : null;
        $aboutVisualImagePath = \App\Models\HomepageSetting::value('about_visual_image_path');
        $aboutVisualImageUrl = $aboutVisualImagePath ? \Illuminate\Support\Facades\Storage::disk('public')->url($aboutVisualImagePath) : null;
    @endphp

    <div class="min-h-screen bg-base-200 text-base-content">
        <x-homepage.navbar :logo-url="$navbarLogoUrl" />

        <main>
            <x-homepage.hero
                :background-image-url="$heroBackgroundImageUrl"
                :content-html="\App\Models\HomepageSetting::value('hero_content')"
                :button-enabled="\App\Models\HomepageSetting::value('hero_button_enabled', '1') === '1'"
                :button-color="\App\Models\HomepageSetting::value('hero_button_color', 'secondary')"
                :button-text="\App\Models\HomepageSetting::value('hero_button_text', 'Pulsante hero')"
                :button-url="\App\Models\HomepageSetting::value('hero_button_url', '#servizi')"
            />

            <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-0">
                <x-homepage.services
                    :label="\App\Models\HomepageSetting::value('services_label')"
                    :left-content-html="\App\Models\HomepageSetting::value('services_left_content_html')"
                    :visual-image-url="$servicesVisualImageUrl"
                    :overlay-content-html="\App\Models\HomepageSetting::value('services_overlay_content_html')"
                    :button-enabled="\App\Models\HomepageSetting::value('services_button_enabled', '1') === '1'"
                    :button-color="\App\Models\HomepageSetting::value('services_button_color', 'primary')"
                    :button-text="\App\Models\HomepageSetting::value('services_button_text', 'Pulsante servizi')"
                    :button-url="\App\Models\HomepageSetting::value('services_button_url', '#catalogo')"
                />
                <x-homepage.about
                    :content-html="\App\Models\HomepageSetting::value('about_content_html')"
                    :visual-image-url="$aboutVisualImageUrl"
                    :button-enabled="\App\Models\HomepageSetting::value('about_button_enabled', '1') === '1'"
                    :button-color="\App\Models\HomepageSetting::value('about_button_color', 'primary')"
                    :button-text="\App\Models\HomepageSetting::value('about_button_text', 'Pulsante contenuti')"
                    :button-url="\App\Models\HomepageSetting::value('about_button_url', '#servizi')"
                />
            </div>
        </main>

        <x-homepage.footer />
    </div>
</x-layouts.app>
