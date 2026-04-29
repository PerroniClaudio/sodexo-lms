<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateHomepageAboutRequest;
use App\Http\Requests\Admin\UpdateHomepageHeroRequest;
use App\Http\Requests\Admin\UpdateHomepageNavigationRequest;
use App\Http\Requests\Admin\UpdateHomepageServicesRequest;
use App\Models\HomepageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomepageCustomizationController extends Controller
{
    public function index(): View
    {
        $logoPath = HomepageSetting::value('navbar_logo_path');
        $heroBackgroundImagePath = HomepageSetting::value('hero_background_image_path');
        $servicesVisualImagePath = HomepageSetting::value('services_visual_image_path');
        $aboutVisualImagePath = HomepageSetting::value('about_visual_image_path');

        return view('admin.homepage.index', [
            'logoPath' => $logoPath,
            'logoUrl' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
            'heroBackgroundImagePath' => $heroBackgroundImagePath,
            'heroBackgroundImageUrl' => $heroBackgroundImagePath ? Storage::disk('public')->url($heroBackgroundImagePath) : null,
            'heroContent' => HomepageSetting::value('hero_content', $this->defaultHeroContent()),
            'heroButtonEnabled' => HomepageSetting::value('hero_button_enabled', '1') === '1',
            'heroButtonColor' => HomepageSetting::value('hero_button_color', 'secondary'),
            'heroButtonText' => HomepageSetting::value('hero_button_text', 'Bottone Hero'),
            'heroButtonUrl' => HomepageSetting::value('hero_button_url', '#'),
            'servicesLabel' => HomepageSetting::value('services_label', 'Servizi'),
            'servicesVisualImagePath' => $servicesVisualImagePath,
            'servicesVisualImageUrl' => $servicesVisualImagePath ? Storage::disk('public')->url($servicesVisualImagePath) : null,
            'servicesLeftContentHtml' => HomepageSetting::value('services_left_content_html', $this->defaultServicesLeftContent()),
            'servicesOverlayContentHtml' => HomepageSetting::value('services_overlay_content_html'),
            'servicesButtonEnabled' => HomepageSetting::value('services_button_enabled', '1') === '1',
            'servicesButtonColor' => HomepageSetting::value('services_button_color', 'primary'),
            'servicesButtonText' => HomepageSetting::value('services_button_text', 'Esplora il nostro catalogo'),
            'servicesButtonUrl' => HomepageSetting::value('services_button_url', '#catalogo'),
            'aboutVisualImagePath' => $aboutVisualImagePath,
            'aboutVisualImageUrl' => $aboutVisualImagePath ? Storage::disk('public')->url($aboutVisualImagePath) : null,
            'aboutContentHtml' => HomepageSetting::value('about_content_html', $this->defaultAboutContent()),
            'aboutButtonEnabled' => HomepageSetting::value('about_button_enabled', '1') === '1',
            'aboutButtonColor' => HomepageSetting::value('about_button_color', 'primary'),
            'aboutButtonText' => HomepageSetting::value('about_button_text', 'Esplora il nostro catalogo'),
            'aboutButtonUrl' => HomepageSetting::value('about_button_url', '#servizi'),
        ]);
    }

    public function updateNavigation(UpdateHomepageNavigationRequest $request): RedirectResponse
    {
        $currentLogoPath = HomepageSetting::value('navbar_logo_path');

        $logoPath = $request->file('logo')->store('homepage/navigation', 'public');

        HomepageSetting::put('navbar_logo_path', $logoPath);

        if ($currentLogoPath !== null && $currentLogoPath !== $logoPath) {
            Storage::disk('public')->delete($currentLogoPath);
        }

        return redirect()
            ->route('admin.homepage.index')
            ->with('status', __('Barra di navigazione aggiornata.'));
    }

    public function updateHero(UpdateHomepageHeroRequest $request): RedirectResponse
    {
        $currentBackgroundImagePath = HomepageSetting::value('hero_background_image_path');

        if ($request->hasFile('background_image')) {
            $backgroundImagePath = $request->file('background_image')->store('homepage/hero', 'public');
            HomepageSetting::put('hero_background_image_path', $backgroundImagePath);

            if ($currentBackgroundImagePath !== null && $currentBackgroundImagePath !== $backgroundImagePath) {
                Storage::disk('public')->delete($currentBackgroundImagePath);
            }
        }

        HomepageSetting::put('hero_content', $this->sanitizeHeroContent($request->string('content')->toString()));
        HomepageSetting::put('hero_button_enabled', $request->boolean('button_enabled') ? '1' : '0');
        HomepageSetting::put('hero_button_color', $request->string('button_color')->toString());
        HomepageSetting::put('hero_button_text', $request->string('button_text')->toString());
        HomepageSetting::put('hero_button_url', $request->string('button_url')->toString());

        return redirect()
            ->route('admin.homepage.index')
            ->with('status', __('Hero aggiornata.'));
    }

    public function updateServices(UpdateHomepageServicesRequest $request): RedirectResponse
    {
        $currentVisualImagePath = HomepageSetting::value('services_visual_image_path');

        if ($request->hasFile('visual_image')) {
            $visualImagePath = $request->file('visual_image')->store('homepage/services', 'public');
            HomepageSetting::put('services_visual_image_path', $visualImagePath);

            if ($currentVisualImagePath !== null && $currentVisualImagePath !== $visualImagePath) {
                Storage::disk('public')->delete($currentVisualImagePath);
            }
        }

        HomepageSetting::put('services_label', $request->string('label')->toString());
        HomepageSetting::put('services_left_content_html', $this->sanitizeRichContent($request->string('left_content_html')->toString()));
        HomepageSetting::put('services_overlay_content_html', $this->sanitizeRichContent($request->string('overlay_content_html')->toString()));
        HomepageSetting::put('services_button_enabled', $request->boolean('button_enabled') ? '1' : '0');
        HomepageSetting::put('services_button_color', $request->string('button_color')->toString());
        HomepageSetting::put('services_button_text', $request->string('button_text')->toString());
        HomepageSetting::put('services_button_url', $request->string('button_url')->toString());

        return redirect()
            ->route('admin.homepage.index')
            ->with('status', __('Servizi aggiornati.'));
    }

    public function updateAbout(UpdateHomepageAboutRequest $request): RedirectResponse
    {
        $currentVisualImagePath = HomepageSetting::value('about_visual_image_path');

        if ($request->hasFile('visual_image')) {
            $visualImagePath = $request->file('visual_image')->store('homepage/about', 'public');
            HomepageSetting::put('about_visual_image_path', $visualImagePath);

            if ($currentVisualImagePath !== null && $currentVisualImagePath !== $visualImagePath) {
                Storage::disk('public')->delete($currentVisualImagePath);
            }
        }

        HomepageSetting::put('about_content_html', $this->sanitizeRichContent($request->string('content_html')->toString()));
        HomepageSetting::put('about_button_enabled', $request->boolean('button_enabled') ? '1' : '0');
        HomepageSetting::put('about_button_color', $request->string('button_color')->toString());
        HomepageSetting::put('about_button_text', $request->string('button_text')->toString());
        HomepageSetting::put('about_button_url', $request->string('button_url')->toString());

        return redirect()
            ->route('admin.homepage.index')
            ->with('status', __('Sezione Chi siamo aggiornata.'));
    }

    private function defaultHeroContent(): string
    {
        return '<h1>La tua piattaforma di E-Learning</h1>';
    }

    private function defaultServicesLeftContent(): string
    {
        return '<h2>I nostri <strong>servizi</strong><br>comprendono</h2><ul><li>Corsi <strong>FAD</strong></li><li>Corsi <strong>RES</strong></li><li>Corsi <strong>FSC</strong></li></ul>';
    }

    private function defaultAboutContent(): string
    {
        return '<p>Chi siamo</p><h2>'.e(config('app.name', 'Laravel')).'<br>organizza <strong>Corsi e<br>Convegni</strong></h2><p>'.e(config('app.name', 'Laravel')).' è un partner qualificato nell\'organizzazione di corsi e convegni, nella formazione a distanza e nella consulenza in ambiti di specifico interesse per il settore sanitario.</p>';
    }

    private function sanitizeHeroContent(string $content): string
    {
        return $this->sanitizeRichContent($content);
    }

    private function sanitizeRichContent(string $content): string
    {
        return strip_tags($content, [
            '<h1>',
            '<h2>',
            '<h3>',
            '<p>',
            '<strong>',
            '<em>',
            '<u>',
            '<a>',
            '<br>',
            '<ul>',
            '<ol>',
            '<li>',
            '<blockquote>',
            '<code>',
            '<pre>',
            '<hr>',
        ]);
    }
}
