<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function actingAsRole(string $role): User
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
        'is_foreigner_or_immigrant' => false,
    ]);

    $user->assignRole($role);

    test()->actingAs($user);

    return $user;
}

function scormZipUpload(array $files): UploadedFile
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'scorm-test-');
    $archive = new ZipArchive;
    $archive->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($files as $path => $contents) {
        if ($contents === null) {
            $archive->addEmptyDir(rtrim($path, '/'));

            continue;
        }

        $archive->addFromString($path, $contents);
    }

    $archive->close();

    return new UploadedFile($temporaryFile, 'package.zip', 'application/zip', null, true);
}

function validScormManifest(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="manifest-1" version="1.2"
    xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2"
    xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
    <metadata>
        <schema>ADL SCORM</schema>
        <schemaversion>1.2</schemaversion>
    </metadata>
    <organizations default="ORG-DEFAULT">
        <organization identifier="ORG-OTHER">
            <title>Other</title>
            <item identifier="ITEM-OTHER" identifierref="RES-OTHER">
                <title>Other Item</title>
            </item>
        </organization>
        <organization identifier="ORG-DEFAULT">
            <title>Default Org</title>
            <item identifier="ITEM-DEFAULT" identifierref="RES-DEFAULT">
                <title>Launch Item</title>
            </item>
        </organization>
    </organizations>
    <resources>
        <resource identifier="RES-OTHER" type="webcontent" adlcp:scormtype="sco" href="other/index.html" />
        <resource identifier="RES-DEFAULT" type="webcontent" adlcp:scormtype="sco" href="lesson/index.html" />
    </resources>
</manifest>
XML;
}

function docxUpload(array $entries): UploadedFile
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'docx-test-');
    $archive = new ZipArchive;
    $archive->open($temporaryFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $defaults = [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
    ];

    foreach (array_merge($defaults, $entries) as $path => $contents) {
        $archive->addFromString($path, $contents);
    }

    $archive->close();

    return new UploadedFile(
        $temporaryFile,
        'template.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true
    );
}
