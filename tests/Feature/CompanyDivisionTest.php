<?php

use App\Models\CompanyDivision;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('allows only superadmins to manage company divisions', function () {
    actingAsRole('admin');

    $this->get(route('admin.company-divisions.index'))->assertRedirect();

    $superadmin = User::factory()->create();
    $superadmin->syncRoles(['superadmin']);
    $this->actingAs($superadmin)->withSession(['active_role' => 'superadmin']);

    $this->get(route('admin.company-divisions.index'))->assertSuccessful();
});

it('stores a company division with admins courses and logo', function () {
    Storage::fake('public');
    $this->seed(RoleAndPermissionSeeder::class);
    $superadmin = User::factory()->create();
    $superadmin->syncRoles(['superadmin']);
    $this->actingAs($superadmin)->withSession(['active_role' => 'superadmin']);
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $user = User::factory()->create();
    $course = Course::factory()->create();

    $this->post(route('admin.company-divisions.store'), [
        'name' => 'Sodexo Nord',
        'vat_number' => '12345678901',
        'logo' => UploadedFile::fake()->image('logo.png'),
        'admin_ids' => [$admin->getKey()],
        'user_ids' => [$user->getKey()],
        'course_ids' => [$course->getKey()],
    ])->assertRedirect();

    $division = CompanyDivision::query()->where('name', 'Sodexo Nord')->firstOrFail();

    expect($division->admins()->whereKey($admin)->exists())->toBeTrue()
        ->and($user->fresh()->company_division_id)->toBe($division->getKey())
        ->and($division->courses()->whereKey($course)->exists())->toBeTrue()
        ->and($division->logo_path)->not->toBeNull();
});

it('renders the company division edit cards', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $superadmin = User::factory()->create();
    $superadmin->syncRoles(['superadmin']);
    $this->actingAs($superadmin)->withSession(['active_role' => 'superadmin']);
    $division = CompanyDivision::factory()->create(['name' => 'Sodexo Nord']);

    $this->get(route('admin.company-divisions.edit', $division))
        ->assertSuccessful()
        ->assertSee('Sodexo Nord')
        ->assertSee('Utenti associati')
        ->assertSee('Admin associati')
        ->assertSee('Corsi associati')
        ->assertSee('type="submit" class="btn btn-primary">Conferma', false)
        ->assertDontSee('data-submit-on-change', false)
        ->assertDontSee('data-association-checkbox', false);
});

it('updates one company division association without clearing the others', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $superadmin = User::factory()->create();
    $superadmin->syncRoles(['superadmin']);
    $this->actingAs($superadmin)->withSession(['active_role' => 'superadmin']);
    $division = CompanyDivision::factory()->create(['name' => 'Sodexo Nord']);
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $oldUser = User::factory()->create(['company_division_id' => $division->getKey()]);
    $newUser = User::factory()->create();
    $course = Course::factory()->create();

    $division->admins()->attach($admin);
    $division->courses()->attach($course);

    $this->put(route('admin.company-divisions.update', $division), [
        'name' => $division->name,
        'sync_users' => true,
        'user_ids' => [$newUser->getKey()],
    ])->assertRedirect();

    expect($oldUser->fresh()->company_division_id)->toBeNull()
        ->and($newUser->fresh()->company_division_id)->toBe($division->getKey())
        ->and($division->admins()->whereKey($admin)->exists())->toBeTrue()
        ->and($division->courses()->whereKey($course)->exists())->toBeTrue();
});

it('selects admin company division after login', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create(['password' => bcrypt('password')]);
    $admin->syncRoles(['admin']);
    $first = CompanyDivision::factory()->create();
    $second = CompanyDivision::factory()->create();
    $admin->administeredCompanyDivisions()->attach([$first->getKey(), $second->getKey()]);

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('company-division.select'));

    $this->post(route('company-division.select.update'), [
        'company_division_id' => $first->getKey(),
    ])->assertRedirect(route('admin.dashboard'));

    expect(session('active_company_division_id'))->toBe($first->getKey());
});

it('filters user courses by company division but keeps global assigned courses', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $division = CompanyDivision::factory()->create();
    $otherDivision = CompanyDivision::factory()->create();
    $user = User::factory()->create(['company_division_id' => $division->getKey()]);
    $visibleCourse = Course::factory()->published()->create(['title' => 'Visible division course']);
    $hiddenCourse = Course::factory()->published()->create(['title' => 'Hidden division course']);
    $globalCourse = Course::factory()->published()->create(['title' => 'Global assigned course']);
    $visibleCourse->companyDivisions()->attach($division);
    $hiddenCourse->companyDivisions()->attach($otherDivision);

    foreach ([$visibleCourse, $hiddenCourse, $globalCourse] as $course) {
        CourseEnrollment::factory()->create([
            'user_id' => $user->getKey(),
            'course_id' => $course->getKey(),
            'direct_origin' => true,
        ]);
    }

    $this->actingAs($user)->withSession(['active_role' => 'user']);

    $this->get(route('user.courses.index'))
        ->assertSuccessful()
        ->assertSee('Visible division course')
        ->assertSee('Global assigned course')
        ->assertDontSee('Hidden division course');

    $this->get(route('user.courses.show', $hiddenCourse))->assertForbidden();
});

it('filters admin courses and users by active company division', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $division = CompanyDivision::factory()->create();
    $otherDivision = CompanyDivision::factory()->create();
    $admin->administeredCompanyDivisions()->attach($division);
    $visibleUser = User::factory()->create(['name' => 'VisibleLearner', 'company_division_id' => $division->getKey()]);
    $hiddenUser = User::factory()->create(['name' => 'HiddenLearner', 'company_division_id' => $otherDivision->getKey()]);
    $visibleCourse = Course::factory()->create(['title' => 'Visible admin course']);
    $hiddenCourse = Course::factory()->create(['title' => 'Hidden admin course']);
    $visibleCourse->companyDivisions()->attach($division);
    $hiddenCourse->companyDivisions()->attach($otherDivision);

    $this->actingAs($admin)->withSession([
        'active_role' => 'admin',
        'active_company_division_id' => $division->getKey(),
    ]);

    $this->get(route('admin.courses.index'))
        ->assertSuccessful()
        ->assertSee('Visible admin course')
        ->assertDontSee('Hidden admin course');

    $this->get(route('admin.users.index'))
        ->assertSuccessful()
        ->assertSee($visibleUser->name)
        ->assertDontSee($hiddenUser->name);
});

it('shows company division logo in user layout', function () {
    Storage::fake('public');
    $this->seed(RoleAndPermissionSeeder::class);
    $division = CompanyDivision::factory()->create(['logo_path' => 'company-divisions/logo.png']);
    Storage::disk('public')->put('company-divisions/logo.png', 'fake');
    $user = User::factory()->create(['company_division_id' => $division->getKey()]);

    $this->actingAs($user)->withSession(['active_role' => 'user']);

    $this->get(route('user.courses.index'))
        ->assertSuccessful()
        ->assertSee($division->logoUrl(), false);
});
