<?php

use App\Models\HomepageSetting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function actingAsHomepageRole(string $role): User
{
    test()->seed(RoleAndPermissionSeeder::class);

    $user = User::query()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => fake()->unique()->regexify('[A-Z0-9]{16}'),
    ]);

    $user->assignRole($role);

    test()->actingAs($user);

    return $user;
}

it('shows the homepage menu item to admins', function () {
    actingAsHomepageRole('admin');

    $response = $this->get(route('admin.homepage.index'));

    $response->assertOk();
    $response->assertSeeText('Home page');
    $response->assertSeeText('Barra di navigazione');
    $response->assertSeeText('Servizi');
    $response->assertSeeText('Chi siamo');
    $response->assertSeeText('Salva servizi');
    $response->assertSeeText('Salva Chi siamo');
    $response->assertDontSeeText('Anteprima immagine');
    $response->assertDontSeeText('Anteprima sfondo');
    $response->assertDontSeeText('Layout di default');
    $response->assertSee(route('admin.homepage.navigation.update'), escape: false);
    $response->assertSee(route('admin.homepage.services.update'), escape: false);
    $response->assertSee(route('admin.homepage.about.update'), escape: false);
    $response->assertSee(route('admin.homepage.index'), escape: false);
    $response->assertSee('<div class="collapse collapse-arrow mt-6 border border-base-300 bg-base-100 shadow-sm" data-homepage-card="navigation">', escape: false);
    $response->assertSee('<div class="collapse collapse-arrow mt-6 border border-base-300 bg-base-100 shadow-sm" data-homepage-card="hero">', escape: false);
    $response->assertSee('<div class="collapse collapse-arrow mt-6 border border-base-300 bg-base-100 shadow-sm" data-homepage-card="services">', escape: false);
    $response->assertSee('<div class="collapse collapse-arrow mt-6 border border-base-300 bg-base-100 shadow-sm" data-homepage-card="about">', escape: false);
    $response->assertSee('<input type="checkbox" data-homepage-card-toggle="navigation">', escape: false);
    $response->assertSee('<input type="checkbox" data-homepage-card-toggle="hero">', escape: false);
    $response->assertSee('<input type="checkbox" data-homepage-card-toggle="services">', escape: false);
    $response->assertSee('<input type="checkbox" data-homepage-card-toggle="about">', escape: false);
    $response->assertDontSee('checked data-homepage-card-toggle', escape: false);
});

it('shows the homepage menu item to superadmins', function () {
    actingAsHomepageRole('superadmin');

    $response = $this->get(route('admin.homepage.index'));

    $response->assertOk();
    $response->assertSeeText('Home page');
    $response->assertSeeText('Servizi');
    $response->assertSee(route('admin.homepage.index'), escape: false);
});

it('does not allow regular users to access the homepage admin page', function () {
    actingAsHomepageRole('user');

    $this->get(route('admin.homepage.index'))
        ->assertRedirect(route('reserved-area'))
        ->assertSessionHas('error', 'Non sei autorizzato ad accedere a questa sezione.');
});

it('allows admins to upload the public homepage navigation logo', function () {
    Storage::fake('public');
    actingAsHomepageRole('admin');

    $logo = UploadedFile::fake()->create('logo.png', 24, 'image/png');

    $this->post(route('admin.homepage.navigation.update'), [
        'logo' => $logo,
    ])
        ->assertRedirect(route('admin.homepage.index'))
        ->assertSessionHas('status', 'Barra di navigazione aggiornata.');

    $logoPath = HomepageSetting::value('navbar_logo_path');

    expect($logoPath)->toBeString()
        ->and($logoPath)->toStartWith('homepage/navigation/');

    Storage::disk('public')->assertExists($logoPath);

    $this->get('/')
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($logoPath), escape: false)
        ->assertSee('alt="Logo"', escape: false);
});

it('allows admins to customize the public homepage hero', function () {
    Storage::fake('public');
    actingAsHomepageRole('admin');

    $backgroundImage = UploadedFile::fake()->create('hero.jpg', 128, 'image/jpeg');
    $content = '<h1>Nuova hero <strong>ECM</strong></h1><h2>Sottotitolo hero</h2><p>Testo centrale aggiornato</p><ul><li>Primo punto</li></ul><script>alert("x")</script>';

    $this->post(route('admin.homepage.hero.update'), [
        'background_image' => $backgroundImage,
        'content' => $content,
        'button_enabled' => '1',
        'button_color' => 'accent',
        'button_text' => 'Scopri ora',
        'button_url' => '/catalogo',
    ])
        ->assertRedirect(route('admin.homepage.index'))
        ->assertSessionHas('status', 'Hero aggiornata.');

    $backgroundImagePath = HomepageSetting::value('hero_background_image_path');

    expect($backgroundImagePath)->toBeString()
        ->and($backgroundImagePath)->toStartWith('homepage/hero/')
        ->and(HomepageSetting::value('hero_content'))->toContain('Nuova hero')
        ->and(HomepageSetting::value('hero_content'))->not->toContain('<script>')
        ->and(HomepageSetting::value('hero_button_enabled'))->toBe('1')
        ->and(HomepageSetting::value('hero_button_color'))->toBe('accent')
        ->and(HomepageSetting::value('hero_button_text'))->toBe('Scopri ora')
        ->and(HomepageSetting::value('hero_button_url'))->toBe('/catalogo');

    Storage::disk('public')->assertExists($backgroundImagePath);

    $this->get('/')
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($backgroundImagePath), escape: false)
        ->assertSee('<h1>Nuova hero <strong>ECM</strong></h1>', escape: false)
        ->assertSee('<h2>Sottotitolo hero</h2>', escape: false)
        ->assertSee('<p>Testo centrale aggiornato</p>', escape: false)
        ->assertSee('<ul><li>Primo punto</li></ul>', escape: false)
        ->assertSee('Scopri ora')
        ->assertSee('href="/catalogo"', escape: false)
        ->assertSee('bg-accent', escape: false);
});

it('allows admins to disable the public homepage hero button', function () {
    actingAsHomepageRole('admin');

    $this->post(route('admin.homepage.hero.update'), [
        'content' => '<h1>Hero senza bottone</h1>',
        'button_enabled' => '0',
        'button_color' => 'primary',
        'button_text' => null,
        'button_url' => null,
    ])->assertRedirect(route('admin.homepage.index'));

    expect(HomepageSetting::value('hero_button_enabled'))->toBe('0');

    $this->get('/')
        ->assertOk()
        ->assertSee('Hero senza bottone', escape: false)
        ->assertDontSee('U20m - Under20Minutes');
});

it('allows admins to customize homepage services settings', function () {
    Storage::fake('public');
    actingAsHomepageRole('admin');

    $visualImage = UploadedFile::fake()->create('services.jpg', 128, 'image/jpeg');

    $this->post(route('admin.homepage.services.update'), [
        'label' => 'Servizi custom',
        'visual_image' => $visualImage,
        'left_content_html' => '<h2>Servizi <strong>nuovi</strong></h2><script>alert(1)</script>',
        'overlay_content_html' => '<p>Overlay <em>libero</em></p><script>alert(2)</script>',
        'button_enabled' => '1',
        'button_color' => 'secondary',
        'button_text' => 'Apri catalogo',
        'button_url' => '/catalogo-servizi',
    ])
        ->assertRedirect(route('admin.homepage.index'))
        ->assertSessionHas('status', 'Servizi aggiornati.');

    $visualImagePath = HomepageSetting::value('services_visual_image_path');

    expect(HomepageSetting::value('services_label'))->toBe('Servizi custom')
        ->and(HomepageSetting::value('services_left_content_html'))->toContain('Servizi <strong>nuovi</strong>')
        ->and(HomepageSetting::value('services_left_content_html'))->not->toContain('<script>')
        ->and(HomepageSetting::value('services_overlay_content_html'))->toContain('Overlay <em>libero</em>')
        ->and(HomepageSetting::value('services_overlay_content_html'))->not->toContain('<script>')
        ->and(HomepageSetting::value('services_button_enabled'))->toBe('1')
        ->and(HomepageSetting::value('services_button_color'))->toBe('secondary')
        ->and(HomepageSetting::value('services_button_text'))->toBe('Apri catalogo')
        ->and(HomepageSetting::value('services_button_url'))->toBe('/catalogo-servizi')
        ->and($visualImagePath)->toBeString()
        ->and($visualImagePath)->toStartWith('homepage/services/');

    Storage::disk('public')->assertExists($visualImagePath);

    $this->get('/')
        ->assertOk()
        ->assertSee('Servizi custom')
        ->assertSee('<h2>Servizi <strong>nuovi</strong></h2>', escape: false)
        ->assertSee('<p>Overlay <em>libero</em></p>', escape: false)
        ->assertSee('Apri catalogo')
        ->assertSee('href="/catalogo-servizi"', escape: false)
        ->assertSee('bg-secondary', escape: false)
        ->assertSee(Storage::disk('public')->url($visualImagePath), escape: false);
});

it('deletes the previous homepage services image when replacing it', function () {
    Storage::fake('public');
    actingAsHomepageRole('admin');

    $firstImage = UploadedFile::fake()->create('services-first.jpg', 64, 'image/jpeg');
    $secondImage = UploadedFile::fake()->create('services-second.jpg', 64, 'image/jpeg');

    $this->post(route('admin.homepage.services.update'), [
        'label' => 'Servizi',
        'visual_image' => $firstImage,
        'left_content_html' => '<p>Prima versione</p>',
        'overlay_content_html' => '<p>Overlay</p>',
        'button_enabled' => '0',
        'button_color' => 'primary',
        'button_text' => null,
        'button_url' => null,
    ])->assertRedirect(route('admin.homepage.index'));

    $firstImagePath = HomepageSetting::value('services_visual_image_path');

    $this->post(route('admin.homepage.services.update'), [
        'label' => 'Servizi',
        'visual_image' => $secondImage,
        'left_content_html' => '<p>Seconda versione</p>',
        'overlay_content_html' => '<p>Overlay</p>',
        'button_enabled' => '0',
        'button_color' => 'primary',
        'button_text' => null,
        'button_url' => null,
    ])->assertRedirect(route('admin.homepage.index'));

    $secondImagePath = HomepageSetting::value('services_visual_image_path');

    expect($secondImagePath)->not->toBe($firstImagePath);

    Storage::disk('public')->assertMissing($firstImagePath);
    Storage::disk('public')->assertExists($secondImagePath);
});

it('renders homepage services fallback when no settings exist', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Servizi')
        ->assertSee('I nostri <strong>servizi</strong> comprendono', escape: false)
        ->assertSee('Corsi <strong>FAD</strong>', escape: false)
        ->assertSee('Corsi <strong>RES</strong>', escape: false)
        ->assertSee('Corsi <strong>FSC</strong>', escape: false)
        ->assertSee('Esplora il nostro catalogo')
        ->assertSee('bg-secondary', escape: false);
});

it('renders homepage services without overlay when overlay content is missing', function () {
    actingAsHomepageRole('admin');

    $this->post(route('admin.homepage.services.update'), [
        'label' => 'Servizi essenziali',
        'left_content_html' => '<p>Solo contenuto sinistro</p>',
        'overlay_content_html' => '',
        'button_enabled' => '0',
        'button_color' => 'neutral',
        'button_text' => null,
        'button_url' => null,
    ])->assertRedirect(route('admin.homepage.index'));

    $this->get('/')
        ->assertOk()
        ->assertSee('Servizi essenziali')
        ->assertSee('<p>Solo contenuto sinistro</p>', escape: false)
        ->assertDontSee('rounded-tl-[2rem] bg-primary p-5 text-primary-content shadow-xl', escape: false)
        ->assertDontSee('href="#catalogo"', escape: false);
});

it('renders homepage services cta with configured color text and link', function () {
    actingAsHomepageRole('admin');

    $this->post(route('admin.homepage.services.update'), [
        'label' => 'Servizi formativi',
        'left_content_html' => '<p>Formazione specialistica</p>',
        'overlay_content_html' => '<p>Supporto dedicato</p>',
        'button_enabled' => '1',
        'button_color' => 'accent',
        'button_text' => 'Contatta il team',
        'button_url' => '/contatti',
    ])->assertRedirect(route('admin.homepage.index'));

    $this->get('/')
        ->assertOk()
        ->assertSee('Contatta il team')
        ->assertSee('href="/contatti"', escape: false)
        ->assertSee('bg-accent', escape: false)
        ->assertSee('<p>Supporto dedicato</p>', escape: false);
});

it('allows admins to customize homepage about settings', function () {
    Storage::fake('public');
    actingAsHomepageRole('admin');

    $visualImage = UploadedFile::fake()->create('about.jpg', 128, 'image/jpeg');

    $this->post(route('admin.homepage.about.update'), [
        'visual_image' => $visualImage,
        'content_html' => '<h2>Chi <strong>siamo</strong></h2><p>Presentazione aggiornata</p><script>alert(1)</script>',
        'button_enabled' => '1',
        'button_color' => 'neutral',
        'button_text' => 'Scopri i servizi',
        'button_url' => '/servizi',
    ])
        ->assertRedirect(route('admin.homepage.index'))
        ->assertSessionHas('status', 'Sezione Chi siamo aggiornata.');

    $visualImagePath = HomepageSetting::value('about_visual_image_path');

    expect(HomepageSetting::value('about_content_html'))->toContain('Chi <strong>siamo</strong>')
        ->and(HomepageSetting::value('about_content_html'))->not->toContain('<script>')
        ->and(HomepageSetting::value('about_button_enabled'))->toBe('1')
        ->and(HomepageSetting::value('about_button_color'))->toBe('neutral')
        ->and(HomepageSetting::value('about_button_text'))->toBe('Scopri i servizi')
        ->and(HomepageSetting::value('about_button_url'))->toBe('/servizi')
        ->and($visualImagePath)->toBeString()
        ->and($visualImagePath)->toStartWith('homepage/about/');

    Storage::disk('public')->assertExists($visualImagePath);

    $this->get('/')
        ->assertOk()
        ->assertSee('<h2>Chi <strong>siamo</strong></h2>', escape: false)
        ->assertSee('<p>Presentazione aggiornata</p>', escape: false)
        ->assertSee('Scopri i servizi')
        ->assertSee('href="/servizi"', escape: false)
        ->assertSee('bg-neutral', escape: false)
        ->assertSee(Storage::disk('public')->url($visualImagePath), escape: false);
});

it('deletes the previous homepage about image when replacing it', function () {
    Storage::fake('public');
    actingAsHomepageRole('admin');

    $firstImage = UploadedFile::fake()->create('about-first.jpg', 64, 'image/jpeg');
    $secondImage = UploadedFile::fake()->create('about-second.jpg', 64, 'image/jpeg');

    $this->post(route('admin.homepage.about.update'), [
        'visual_image' => $firstImage,
        'content_html' => '<p>Prima versione</p>',
        'button_enabled' => '0',
        'button_color' => 'primary',
        'button_text' => null,
        'button_url' => null,
    ])->assertRedirect(route('admin.homepage.index'));

    $firstImagePath = HomepageSetting::value('about_visual_image_path');

    $this->post(route('admin.homepage.about.update'), [
        'visual_image' => $secondImage,
        'content_html' => '<p>Seconda versione</p>',
        'button_enabled' => '0',
        'button_color' => 'primary',
        'button_text' => null,
        'button_url' => null,
    ])->assertRedirect(route('admin.homepage.index'));

    $secondImagePath = HomepageSetting::value('about_visual_image_path');

    expect($secondImagePath)->not->toBe($firstImagePath);

    Storage::disk('public')->assertMissing($firstImagePath);
    Storage::disk('public')->assertExists($secondImagePath);
});

it('renders homepage about fallback when no settings exist', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Chi siamo')
        ->assertSee(config('app.name', 'Laravel'))
        ->assertSee('Corsi e', escape: false)
        ->assertSee('Convegni', escape: false)
        ->assertSee('Esplora il nostro catalogo')
        ->assertSee('href="#servizi"', escape: false)
    ->assertSee('aspect-video bg-secondary', escape: false)
        ->assertSee('bg-primary', escape: false);
});
