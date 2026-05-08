# Module and Course Validation Architecture

## 📋 Overview

This document describes the clean, scalable validation and status management system for Modules and Courses in the LMS application.

The architecture follows SOLID principles with:
- **Services** for validation logic
- **Validators** using Strategy Pattern
- **Observers** for enforcing business rules
- **No logic in controllers**

---

## 🏗️ Architecture

### Directory Structure

```
app/
├── Services/
│   ├── ModuleValidation/
│   │   ├── Contracts/
│   │   │   └── ModuleValidatorInterface.php
│   │   ├── Validators/
│   │   │   ├── VideoValidator.php
│   │   │   ├── LearningQuizValidator.php
│   │   │   ├── LiveValidator.php
│   │   │   ├── SatisfactionQuizValidator.php
│   │   │   ├── ResourceValidator.php
│   │   │   └── ScormValidator.php
│   │   └── ModuleValidatorService.php
│   └── CourseValidation/
│       └── CourseValidatorService.php
├── Observers/
│   ├── ModuleObserver.php
│   └── CourseObserver.php
└── Http/Controllers/Admin/Examples/
    ├── ModuleExampleController.php
    └── CourseExampleController.php
```

---

## 🔍 Components

### 1. ModuleValidatorInterface

Contract that all module validators must implement.

**Methods:**
- `validate(Module $module): bool` - Validate the module
- `getErrors(): array` - Get validation error messages

### 2. Individual Validators

Each module type has its own validator class:

#### VideoValidator
- ✅ Must have title
- ✅ Must have associated video
- ✅ Video must be in "ready" status

#### LearningQuizValidator
- ✅ Must have at least 1 valid question
- ✅ Each question must have exactly 4 answers
- ✅ Each question must have exactly 1 correct answer
- ✅ `max_score` = sum of valid question points
- ✅ `passing_score` ≤ `max_score`
- ✅ `max_attempts` > 0

#### LiveValidator
- ✅ Always valid (for now)

#### SatisfactionQuizValidator
- ✅ Always valid (for now)

#### ResourceValidator
- ✅ Always valid (for now)

#### ScormValidator
- ✅ Always valid (for now)

### 3. ModuleValidatorService

Main service that orchestrates module validation using the Strategy Pattern.

**Methods:**
- `validate(Module $module): bool` - Validate a module based on its type
- `getValidationErrors(Module $module): array` - Get validation errors
- `registerValidator(string $type, string $validatorClass): void` - Register custom validators

**Usage:**
```php
use App\Services\ModuleValidation\ModuleValidatorService;

$validator = app(ModuleValidatorService::class);

if ($validator->validate($module)) {
    // Module is valid
} else {
    $errors = $validator->getValidationErrors($module);
    // Handle errors
}
```

### 4. CourseValidatorService

Service for validating courses.

**Methods:**
- `validate(Course $course): bool` - Check if all modules are valid
- `isPublishable(Course $course): bool` - Check if course can be published (all modules valid AND published)
- `getValidationErrors(Course $course): array` - Get detailed validation errors
- `getPublishabilityErrors(Course $course): array` - Get errors preventing publication

**Usage:**
```php
use App\Services\CourseValidation\CourseValidatorService;

$validator = app(CourseValidatorService::class);

if ($validator->isPublishable($course)) {
    $course->status = 'published';
    $course->save();
} else {
    $errors = $validator->getPublishabilityErrors($course);
    // Handle errors
}
```

---

## 🛡️ Business Rules (Enforced by Observers)

### ModuleObserver

**Prevents:**
1. ❌ Publishing invalid modules
2. ❌ Changing module status if course is published
3. ❌ Editing module data if module is published
4. ❌ Unpublishing modules with enrollments (including trashed)
5. ❌ Any changes to module if course is published

**Events:**
- `saving` - Blocks changes if course is published or module data is modified when published
- `updating` - Validates status transitions

### CourseObserver

**Prevents:**
1. ❌ Publishing courses with invalid modules
2. ❌ Publishing courses with unpublished modules
3. ❌ Editing course data if course is published
4. ❌ Unpublishing courses with enrollments (including trashed)

**Events:**
- `saving` - Blocks data changes when published
- `updating` - Validates status transitions

---

## 🎯 Usage Examples

### Example 1: Validate a Module

```php
use App\Services\ModuleValidation\ModuleValidatorService;

public function __construct(
    private readonly ModuleValidatorService $moduleValidator
) {}

public function validateModule(Module $module)
{
    if (!$this->moduleValidator->validate($module)) {
        $errors = $this->moduleValidator->getValidationErrors($module);
        
        return response()->json([
            'valid' => false,
            'errors' => $errors
        ], 422);
    }
    
    return response()->json(['valid' => true]);
}
```

### Example 2: Publish a Module

```php
// The observer automatically validates
public function publish(Module $module)
{
    try {
        $module->status = 'published';
        $module->save(); // Observer checks if valid
        
        return response()->json(['success' => true]);
    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 422);
    }
}
```

### Example 3: Publish a Course

```php
use App\Services\CourseValidation\CourseValidatorService;

public function publish(Course $course)
{
    try {
        $course->status = 'published';
        $course->save(); // Observer validates all modules
        
        return response()->json(['success' => true]);
    } catch (\RuntimeException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 422);
    }
}
```

### Example 4: Get Validation Report

```php
use App\Services\CourseValidation\CourseValidatorService;

public function __construct(
    private readonly CourseValidatorService $courseValidator
) {}

public function report(Course $course)
{
    return response()->json([
        'is_valid' => $this->courseValidator->validate($course),
        'is_publishable' => $this->courseValidator->isPublishable($course),
        'validation_errors' => $this->courseValidator->getValidationErrors($course),
        'publishability_errors' => $this->courseValidator->getPublishabilityErrors($course),
    ]);
}
```

---

## 🔧 Extending the System

### Adding a New Module Type

1. Create a new validator:

```php
namespace App\Services\ModuleValidation\Validators;

use App\Models\Module;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;

class CustomValidator implements ModuleValidatorInterface
{
    private array $errors = [];

    public function validate(Module $module): bool
    {
        $this->errors = [];
        
        // Your validation logic here
        if (!$module->custom_field) {
            $this->errors[] = 'Custom field is required.';
        }
        
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

2. Register it in a service provider:

```php
use App\Services\ModuleValidation\ModuleValidatorService;
use App\Services\ModuleValidation\Validators\CustomValidator;

public function boot(): void
{
    $validator = app(ModuleValidatorService::class);
    $validator->registerValidator('custom_type', CustomValidator::class);
}
```

---

## ✅ Testing

See the example controllers in:
- `app/Http/Controllers/Admin/Examples/ModuleExampleController.php`
- `app/Http/Controllers/Admin/Examples/CourseExampleController.php`

---

## 📝 Notes & Assumptions

### Assumptions Made:
1. Module `video_id` field exists and references the Video model
2. Video has `mux_video_status` field with "ready" value when ready
3. ModuleQuizQuestion has `isValid()` method checking 4 answers + correct answer
4. Course enrollments are checked via `enrollments()` relationship
5. Both Module and Course use soft deletes for enrollments

### Migration from Old Code:
- Removed `isValidQuiz()` from Module model (now in LearningQuizValidator)
- Validation logic moved from models to services
- Business rules enforcement moved to observers

### Error Handling:
- Observers throw `RuntimeException` when rules are violated
- Services return boolean + error arrays
- Controllers catch exceptions and return appropriate HTTP responses

---

## 🔐 Security Considerations

1. **Authorization**: Controllers should use policies/gates before operations
2. **Input Validation**: Use Form Requests for user input before model operations
3. **Transaction Safety**: Consider wrapping multi-model operations in DB transactions

---

## 🚀 Performance

- Validators load relationships efficiently
- Observers use `withTrashed()` for enrollment checks
- Services use dependency injection for testability

---

## 📞 Support

For questions or issues, refer to:
- Laravel documentation: https://laravel.com/docs
- Project guidelines: AGENTS.md
