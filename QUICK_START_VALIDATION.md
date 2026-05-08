# 🚀 Quick Start Guide - Validation System

## How to Use the Module and Course Validation System

This is a quick reference guide for developers using the validation system.

---

## 📦 What You Get

✅ **Automatic validation** via Observers (no manual checks needed)  
✅ **Manual validation** via Services (for UI logic)  
✅ **Clear error messages** in Italian  
✅ **Type-safe code** with full PHP typing  
✅ **Extensible architecture** for new module types  

---

## 🎯 Common Use Cases

### 1. Publishing a Module

The observer automatically validates. Just update the status:

```php
// In your controller
public function publish(Module $module)
{
    try {
        $module->status = 'published';
        $module->save(); // Observer checks validity automatically
        
        return redirect()->back()->with('success', 'Modulo pubblicato!');
    } catch (\RuntimeException $e) {
        return redirect()->back()->with('error', $e->getMessage());
    }
}
```

### 2. Check if Module is Valid (for UI)

Use the service to check before showing the publish button:

```php
use App\Services\ModuleValidation\ModuleValidatorService;

public function __construct(
    private readonly ModuleValidatorService $moduleValidator
) {}

public function edit(Module $module)
{
    $canPublish = $this->moduleValidator->validate($module);
    $errors = $canPublish ? [] : $this->moduleValidator->getValidationErrors($module);
    
    return view('admin.modules.edit', [
        'module' => $module,
        'canPublish' => $canPublish,
        'validationErrors' => $errors,
    ]);
}
```

### 3. Publishing a Course

Same pattern - observer handles validation:

```php
public function publish(Course $course)
{
    try {
        $course->status = 'published';
        $course->save(); // Observer validates all modules
        
        return redirect()->back()->with('success', 'Corso pubblicato!');
    } catch (\RuntimeException $e) {
        // Error message already includes which modules are invalid
        return redirect()->back()->with('error', $e->getMessage());
    }
}
```

### 4. Check Course Publishability (for UI)

```php
use App\Services\CourseValidation\CourseValidatorService;

public function __construct(
    private readonly CourseValidatorService $courseValidator
) {}

public function show(Course $course)
{
    $isPublishable = $this->courseValidator->isPublishable($course);
    $errors = $isPublishable ? [] : $this->courseValidator->getPublishabilityErrors($course);
    
    return view('admin.courses.show', [
        'course' => $course,
        'isPublishable' => $isPublishable,
        'publishabilityErrors' => $errors,
    ]);
}
```

### 5. Get Validation Report (Admin Dashboard)

```php
use App\Services\CourseValidation\CourseValidatorService;

public function validationReport(Course $course)
{
    $validator = app(CourseValidatorService::class);
    
    return view('admin.courses.validation-report', [
        'course' => $course,
        'isValid' => $validator->validate($course),
        'isPublishable' => $validator->isPublishable($course),
        'validationErrors' => $validator->getValidationErrors($course),
        'publishabilityErrors' => $validator->getPublishabilityErrors($course),
        'publishedModulesCount' => $course->modules->where('status', 'published')->count(),
        'totalModulesCount' => $course->modules->count(),
    ]);
}
```

---

## 🛡️ What the Observers Prevent

### Module Observer

❌ **Publishing invalid module**
```php
$module->status = 'published'; // Will throw if module is invalid
$module->save();
```

❌ **Editing published module**
```php
$module->title = 'New Title'; // Will throw if module is published
$module->save();
```

❌ **Unpublishing module with enrollments**
```php
$module->status = 'draft'; // Will throw if course has enrollments
$module->save();
```

❌ **Changing module when course is published**
```php
// Any change to module will throw if course is published
$module->update(['title' => 'New']); // Will throw
```

### Course Observer

❌ **Publishing course with invalid/unpublished modules**
```php
$course->status = 'published'; // Will throw if any module is invalid or not published
$course->save();
```

❌ **Editing published course**
```php
$course->title = 'New Title'; // Will throw if course is published
$course->save();
```

❌ **Unpublishing course with enrollments**
```php
$course->status = 'draft'; // Will throw if course has enrollments
$course->save();
```

---

## 📋 Validation Rules Quick Reference

### Video Module
- ✅ Has title
- ✅ Has video (`video_id`)
- ✅ Video status is "ready"

### Learning Quiz Module
- ✅ At least 1 valid question
- ✅ Each question has 4 answers
- ✅ Each question has 1 correct answer
- ✅ `max_score` = sum of valid question points
- ✅ `passing_score` ≤ `max_score`
- ✅ `max_attempts` > 0

### Other Modules (Live, Satisfaction Quiz, Resource, SCORM)
- ✅ Always valid (for now)

### Course
- ✅ Has at least 1 module
- ✅ **Valid if:** all modules are valid
- ✅ **Publishable if:** all modules are valid AND published

---

## 🔧 Blade Examples

### Show Publish Button Only If Valid

```blade
@if($canPublish)
    <form method="POST" action="{{ route('admin.modules.publish', $module) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-primary">
            Pubblica Modulo
        </button>
    </form>
@else
    <div class="alert alert-warning">
        <strong>Impossibile pubblicare:</strong>
        <ul>
            @foreach($validationErrors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
```

### Show Course Status with Validation Info

```blade
<div class="card">
    <div class="card-header">
        <h3>{{ $course->title }}</h3>
        <span class="badge badge-{{ $course->status === 'published' ? 'success' : 'warning' }}">
            {{ $course->status }}
        </span>
    </div>
    <div class="card-body">
        <p>
            <strong>Moduli:</strong> 
            {{ $publishedModulesCount }} / {{ $totalModulesCount }} pubblicati
        </p>
        
        @if($isPublishable && $course->status !== 'published')
            <form method="POST" action="{{ route('admin.courses.publish', $course) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-success">
                    Pubblica Corso
                </button>
            </form>
        @elseif(!$isPublishable)
            <div class="alert alert-info">
                <strong>Prima di pubblicare, risolvi questi problemi:</strong>
                <ul>
                    @foreach($publishabilityErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
```

---

## 🧪 Testing Your Changes

After modifying validation logic, run tests:

```bash
# Test module validation
php artisan test tests/Unit/ModuleValidatorServiceTest.php

# Test course validation
php artisan test tests/Unit/CourseValidatorServiceTest.php

# Test observer business rules
php artisan test tests/Feature/ModuleObserverTest.php
php artisan test tests/Feature/CourseObserverTest.php

# Run all validation tests
php artisan test --filter=Validator
php artisan test --filter=Observer
```

---

## 🎨 Adding a New Module Type

1. **Create the validator:**

```php
// app/Services/ModuleValidation/Validators/CustomValidator.php
namespace App\Services\ModuleValidation\Validators;

use App\Models\Module;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;

class CustomValidator implements ModuleValidatorInterface
{
    private array $errors = [];

    public function validate(Module $module): bool
    {
        $this->errors = [];
        
        // Add your validation rules
        if (! $module->custom_field) {
            $this->errors[] = 'Il campo custom_field è obbligatorio.';
        }
        
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

2. **Register it in AppServiceProvider:**

```php
use App\Services\ModuleValidation\ModuleValidatorService;
use App\Services\ModuleValidation\Validators\CustomValidator;

public function boot(): void
{
    // ... existing code ...
    
    $moduleValidator = app(ModuleValidatorService::class);
    $moduleValidator->registerValidator('custom_type', CustomValidator::class);
}
```

3. **Done!** The system will now validate your new module type.

---

## ⚠️ Important Notes

### Do NOT bypass observers

❌ **Wrong:**
```php
$module->status = 'published';
$module->saveQuietly(); // Bypasses observer - DON'T DO THIS
```

✅ **Correct:**
```php
try {
    $module->status = 'published';
    $module->save(); // Observer validates automatically
} catch (\RuntimeException $e) {
    // Handle error
}
```

### Always catch RuntimeException

When changing status, always wrap in try-catch:

```php
try {
    $course->status = 'published';
    $course->save();
} catch (\RuntimeException $e) {
    // Observer threw exception - validation failed
    return back()->with('error', $e->getMessage());
}
```

### Use services for UI logic

Don't call validators directly in views. Use controllers:

✅ **Good:**
```php
// Controller
$canPublish = $this->moduleValidator->validate($module);
return view('...', compact('canPublish'));
```

❌ **Bad:**
```blade
{{-- View --}}
@if(app(ModuleValidatorService::class)->validate($module))
    ...
@endif
```

---

## 📞 Need Help?

- See full documentation: `VALIDATION_ARCHITECTURE.md`
- See implementation summary: `IMPLEMENTATION_SUMMARY.md`
- See example controllers: `app/Http/Controllers/Admin/Examples/`
- See tests: `tests/Unit/` and `tests/Feature/`

---

## ✅ Checklist for Publishing

Before publishing a course:

1. ✅ All modules have valid data (use validation service to check)
2. ✅ All modules are published
3. ✅ Course has no validation errors
4. ✅ Update course status to 'published' (observer validates automatically)

Before publishing a module:

1. ✅ Module has all required data for its type
2. ✅ Module passes validation (use validation service to check)
3. ✅ Update module status to 'published' (observer validates automatically)

---

**Happy coding! 🚀**
