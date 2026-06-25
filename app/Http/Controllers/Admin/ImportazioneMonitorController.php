<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Importazione;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportazioneMonitorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $status = trim($request->string('status')->toString());
        $type = trim($request->string('type')->toString());

        $importazioni = Importazione::query()
            ->with('creator:id,name,surname,email')
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($type !== '', fn ($builder) => $builder->where('import_type', $type))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($builder) use ($search) {
                    $builder
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('file_path', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%")
                        ->orWhereHas('creator', function ($builder) use ($search) {
                            $builder
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('surname', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.importazioni-monitor.index', [
            'importazioni' => $importazioni,
            'selectedStatus' => $status,
            'selectedType' => $type,
            'statuses' => [
                Importazione::STATUS_PENDING,
                Importazione::STATUS_PROGRESS,
                Importazione::STATUS_FINISHED,
                Importazione::STATUS_FAILED,
            ],
            'types' => Importazione::availableTypes(),
            'tableSearch' => $search,
        ]);
    }

    public function download(Importazione $importazione): StreamedResponse
    {
        $disk = Storage::disk(Importazione::STORAGE_DISK);

        abort_unless($disk->exists($importazione->file_path), 404);

        return $disk->download(
            $importazione->file_path,
            $importazione->fileName(),
        );
    }
}
