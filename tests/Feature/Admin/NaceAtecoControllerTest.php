<?php

use App\Enums\HierarchyLevel;
use App\Enums\RiskLevel;
use App\Enums\UserStatus;
use App\Models\NaceAteco;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crea un utente admin semplice senza factory
    $this->admin = User::create([
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'name' => 'Admin',
        'surname' => 'Test',
        'fiscal_code' => 'ADMTST80A01H501Z',
        'account_state' => UserStatus::ACTIVE,
    ]);

    Role::create(['name' => 'admin']);
    $this->admin->assignRole('admin');
});

test('admin can access nace ateco index page', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.nace-ateco.index'));

    $response->assertStatus(200);
    $response->assertViewIs('admin.nace-ateco.index');
});

test('nace ateco index displays hierarchical structure', function () {
    // Crea una gerarchia di test
    $section = NaceAteco::create([
        'code' => 'A',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SECTION,
        'title_it' => 'Sezione A',
        'title_en' => 'Section A',
    ]);

    $division = NaceAteco::create([
        'code' => 'A.01',
        'order' => 2,
        'hierarchy' => HierarchyLevel::DIVISION,
        'title_it' => 'Divisione 01',
        'title_en' => 'Division 01',
    ]);

    $ateco = NaceAteco::create([
        'code' => 'A.01.11.10',
        'order' => 3,
        'hierarchy' => HierarchyLevel::SUBCATEGORY,
        'title_it' => 'Coltivazione cereali',
        'title_en' => 'Growing of cereals',
        'risk' => RiskLevel::LOW,
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.nace-ateco.index'));

    $response->assertSee('Sezione A');
    $response->assertSee('A.01.11.10');
    $response->assertSee('Coltivazione cereali');
});

test('nace ateco index can search by code', function () {
    NaceAteco::create([
        'code' => 'TEST001',
        'order' => 1,
        'hierarchy' => HierarchyLevel::NACE_CLASS,
        'title_it' => 'Test codice',
        'title_en' => 'Test code',
    ]);

    NaceAteco::create([
        'code' => 'OTHER002',
        'order' => 2,
        'hierarchy' => HierarchyLevel::NACE_CLASS,
        'title_it' => 'Altro codice',
        'title_en' => 'Other code',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.nace-ateco.index', ['search' => 'TEST']));

    $response->assertSee('TEST001');
    $response->assertDontSee('OTHER002');
});

test('nace ateco index can search by title', function () {
    NaceAteco::create([
        'code' => 'CODE001',
        'order' => 1,
        'hierarchy' => HierarchyLevel::NACE_CLASS,
        'title_it' => 'Ristorazione alberghiera',
        'title_en' => 'Hotel catering',
    ]);

    NaceAteco::create([
        'code' => 'CODE002',
        'order' => 2,
        'hierarchy' => HierarchyLevel::NACE_CLASS,
        'title_it' => 'Meccanica industriale',
        'title_en' => 'Industrial mechanics',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.nace-ateco.index', ['search' => 'Ristorazione']));

    $response->assertSee('Ristorazione alberghiera');
    $response->assertDontSee('Meccanica industriale');
});
