<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiveStreamSessionLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LiveStreamLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());

        $logs = LiveStreamSessionLog::query()
            ->with(['module:id,title', 'teacher:id,name,surname'])
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($builder) use ($search) {
                    $builder
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('twilio_room_name', 'like', "%{$search}%")
                        ->orWhereHas('module', function ($builder) use ($search) {
                            $builder->where('title', 'like', "%{$search}%");
                        })
                        ->orWhereHas('teacher', function ($builder) use ($search) {
                            $builder
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('surname', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('exported_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.live-stream-logs.index', [
            'logs' => $logs,
            'tableSearch' => $search,
        ]);
    }

    public function show(Request $request, LiveStreamSessionLog $liveStreamLog): View
    {
        $entries = $this->readEntries($liveStreamLog);
        $selectedType = trim($request->string('type')->toString());
        $search = trim($request->string('search')->toString());
        $filteredEntries = $entries
            ->when($selectedType !== '', fn (Collection $entries): Collection => $entries->where('type', $selectedType)->values())
            ->when($search !== '', function (Collection $entries) use ($search): Collection {
                return $entries->filter(function (array $entry) use ($search): bool {
                    return str_contains(mb_strtolower(json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), mb_strtolower($search));
                })->values();
            });
        $currentPage = max(1, $request->integer('page', 1));
        $perPage = 100;
        $paginatedEntries = new LengthAwarePaginator(
            $filteredEntries->forPage($currentPage, $perPage)->values(),
            $filteredEntries->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return view('admin.live-stream-logs.show', [
            'log' => $liveStreamLog->loadMissing(['module:id,title', 'teacher:id,name,surname', 'session:id,started_at,ended_at,twilio_room_name']),
            'entries' => $paginatedEntries,
            'entryTypes' => $entries->pluck('type')->filter()->unique()->sort()->values(),
            'selectedType' => $selectedType,
            'tableSearch' => $search,
        ]);
    }

    public function download(LiveStreamSessionLog $liveStreamLog): RedirectResponse
    {
        abort_unless(Storage::disk($liveStreamLog->disk)->exists($liveStreamLog->path), 404);

        return redirect()->away(
            Storage::disk($liveStreamLog->disk)->temporaryUrl(
                $liveStreamLog->path,
                now()->addMinutes(10),
            ),
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function readEntries(LiveStreamSessionLog $liveStreamLog): Collection
    {
        abort_unless(Storage::disk($liveStreamLog->disk)->exists($liveStreamLog->path), 404);

        $payload = json_decode(Storage::disk($liveStreamLog->disk)->get($liveStreamLog->path), true);

        abort_unless(is_array($payload), 404);

        return collect($payload['events'] ?? [])
            ->filter(fn ($entry): bool => is_array($entry))
            ->values();
    }
}
