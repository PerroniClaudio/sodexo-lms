<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaviconRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class OtherConfigurationController extends Controller
{
    public function index(): View
    {
        return view('admin.other-configurations.index', [
            'faviconUrl' => $this->faviconUrl(),
        ]);
    }

    public function storeFavicon(StoreFaviconRequest $request): JsonResponse
    {
        try {
            $request->file('favicon')->move(public_path(), 'favicon.ico');
        } catch (FileException) {
            return response()->json([
                'message' => __('Impossibile salvare la favicon. Verifica i permessi della cartella public.'),
            ], 500);
        }

        return response()->json([
            'message' => __('Favicon aggiornata.'),
            'favicon_url' => $this->faviconUrl(),
        ]);
    }

    private function faviconUrl(): ?string
    {
        $faviconPath = public_path('favicon.ico');

        if (! is_file($faviconPath) || filesize($faviconPath) === 0) {
            return null;
        }

        return asset('favicon.ico').'?v='.filemtime($faviconPath);
    }
}
