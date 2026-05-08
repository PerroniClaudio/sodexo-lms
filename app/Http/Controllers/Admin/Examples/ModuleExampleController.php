<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\ModuleValidation\ModuleValidatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Example controller showing how to use ModuleValidatorService and observers.
 */
class ModuleController extends Controller
{
    public function __construct(
        private readonly ModuleValidatorService $moduleValidator
    ) {}

    /**
     * Validate a module.
     *
     * This shows how to manually check if a module is valid before attempting operations.
     */
    public function validateModule(Module $module): JsonResponse
    {
        $isValid = $this->moduleValidator->validate($module);

        if (! $isValid) {
            $errors = $this->moduleValidator->getValidationErrors($module);

            return response()->json([
                'valid' => false,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Il modulo è valido.',
        ]);
    }

    /**
     * Publish a module.
     *
     * The observer will automatically validate if the module can be published.
     * If not valid, it will throw a RuntimeException.
     */
    public function publish(Module $module): JsonResponse
    {
        try {
            $module->status = 'published';
            $module->save();

            return response()->json([
                'success' => true,
                'message' => 'Modulo pubblicato con successo.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unpublish a module.
     *
     * The observer will automatically check if unpublishing is allowed.
     */
    public function unpublish(Module $module): JsonResponse
    {
        try {
            $module->status = 'draft';
            $module->save();

            return response()->json([
                'success' => true,
                'message' => 'Pubblicazione rimossa con successo.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a module.
     *
     * The observer will prevent updates if:
     * - The module is published
     * - The course is published
     */
    public function update(Request $request, Module $module): JsonResponse
    {
        try {
            $module->update($request->only(['title', 'description']));

            return response()->json([
                'success' => true,
                'message' => 'Modulo aggiornato con successo.',
                'module' => $module,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check module validity before showing publish button.
     *
     * This is useful for UI logic to show/hide publish buttons.
     */
    public function checkPublishable(Module $module): JsonResponse
    {
        $isValid = $this->moduleValidator->validate($module);
        $errors = $isValid ? [] : $this->moduleValidator->getValidationErrors($module);

        return response()->json([
            'publishable' => $isValid,
            'errors' => $errors,
        ]);
    }
}
