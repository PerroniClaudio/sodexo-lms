<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoRequest;
use App\Jobs\SyncMuxVideosStatusJob;
use App\Models\Video;
use App\Services\MuxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VideoController extends Controller
{
    /**
     * Restituisce una signed thumbnail URL per la preview statica
     */
    public function signedThumbnail(Video $video, MuxService $muxService)
    {
        abort_unless($video->mux_playback_id && $video->mux_video_status === 'ready', 404);
        // URL thumbnail Mux: https://image.mux.com/{PLAYBACK_ID}/thumbnail.jpg
        $baseUrl = "https://image.mux.com/{$video->mux_playback_id}/thumbnail.jpg";
        // Se la policy è signed, serve firmare anche la thumbnail
        $signedUrl = $muxService->getSignedThumbnailUrl($video->mux_playback_id);
        return redirect($signedUrl);
    }

    /**
     * API: restituisce signed playback URL per preview modal
     */
    public function signedPlaybackApi(Video $video, MuxService $muxService)
    {
        if (!$video->mux_playback_id || $video->mux_video_status !== 'ready') {
            return response()->json(['url' => null], 404);
        }
        $playbackId = $video->mux_playback_id;
        $token = $muxService->generateJwtToken($playbackId, time() + 3600, 'v'); // aud = 'v' per video playback
        return response()->json([
            'playback_id' => $playbackId,
            'token' => $token,
        ]);
    }
    public function index(Request $request)
    {
        $query = Video::withCount('modules');

        // Ricerca globale
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                  ->orWhere('mux_video_status', 'like', "%$search%");
            });
        }

        // Soft delete: mostra eliminati se richiesto
        if ($request->boolean('show_trashed')) {
            $query->withTrashed();
        }

        // Ordinamento
        $sortable = ['title', 'mux_video_status', 'modules_count', 'status'];
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        if (!in_array($sort, $sortable)) {
            $sort = 'created_at';
        }
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }
        $query->orderBy($sort, $direction);

        $videos = $query->paginate(20)->withQueryString();

        return view('admin.videos.index', [
            'videos' => $videos,
            'sort' => $sort,
            'direction' => $direction,
            'search' => $search,
        ]);
    }

    /**
     * API: Restituisce la lista video per la tabella video-table (paginata, ricerca, ordinamento)
     */
    public function indexApi(Request $request)
    {
        $query = Video::withCount('modules');

        // Ricerca globale
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('mux_video_status', 'like', "%$search%")
                    ->orWhereHas('modules', function ($q2) use ($search) {
                        $q2->where('title', 'like', "%$search%")
                            ->orWhere('id', 'like', "%$search%")
                            ->orWhere('status', 'like', "%$search%");
                    });
            });
        }

        // Ordinamento
        $sortable = ['title', 'mux_video_status', 'modules_count', 'status'];
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        if (!in_array($sort, $sortable)) {
            $sort = 'created_at';
        }
        if (!in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }
        $query->orderBy($sort, $direction);

        $videos = $query->paginate(10);

        // Struttura compatibile con la tabella video-table
        $videos->getCollection()->transform(function ($video) {
            return [
                'id' => $video->id,
                'title' => $video->title,
                'modules_count' => $video->modules_count,
                'mux_video_status' => $video->mux_video_status,
                'trashed_at' => $video->trashed_at,
            ];
        });

        return response()->json($videos);
    }

    /**
     * API: Restituisce le informazioni di un singolo video
     */
    public function getInfoApi(Video $video)
    {
        // Recupera durata da Mux tramite il service
        $duration = null;
        if ($video->mux_asset_id) {
            $muxService = app(\App\Services\MuxService::class);
            $durationRaw = $muxService->getAssetDuration($video->mux_asset_id);
            $duration = is_numeric($durationRaw) ? (int) round($durationRaw) : null;
        }
        return response()->json([
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'mux_video_status' => $video->mux_video_status,
            'modules_count' => $video->modules()->count(),
            'trashed_at' => $video->trashed_at,
            'duration' => $duration,
        ]);
    }

    public function store(StoreVideoRequest $request, MuxService $muxService)
    {
        $request->validated();
        // Crea direct upload su Mux
        $mux = $muxService->createDirectUpload($request->file('video_file')->getClientOriginalName());
        $video = Video::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'mux_upload_id' => $mux['upload_id'],
            'mux_video_status' => 'uploading',
            'mux_asset_id' => null,
            'mux_playback_id' => null,
        ]);
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'upload_url' => $mux['url'],
                'video_id' => $video->id,
            ]);
        }

        return redirect()->back()->with('success', 'Video in caricamento. L’upload diretto è stato avviato.');
    }

    /**
     * Restituisce un signed playback URL per la preview sicura del video
     */
    public function signedPlayback(Video $video, MuxService $muxService)
    {
        abort_unless($video->mux_playback_id && $video->mux_video_status === 'ready', 404);
        $url = $muxService->getSignedPlaybackUrl($video->mux_playback_id);

        return redirect($url);
    }

    public function create()
    {
        // Mostra form di caricamento video
        return view('admin.videos.create');
    }

    public function edit(Video $video)
    {
        // Mostra form di modifica video
        return view('admin.videos.edit', compact('video'));
    }

    public function update(Request $request, Video $video)
    {
        // Aggiorna dati video
        $video->update($request->only(['title', 'description']));

        return redirect()->route('admin.videos.index')->with('success', 'Video aggiornato');
    }

    public function destroy(Video $video)
    {
        $video->delete();

        return redirect()->route('admin.videos.index')->with('success', 'Video eliminato');
    }

    public function restore($id)
    {
        $video = Video::withTrashed()->findOrFail($id);
        $video->restore();

        return redirect()->route('admin.videos.index')->with('success', 'Video ripristinato');
    }

    /**
     * Avvia la sincronizzazione manuale dello stato video Mux tramite Job
     */
    public function syncMuxStatus()
    {
        // Usa Cache come lock per evitare dispatch multipli
        $lockKey = 'sync-mux-videos-dispatch-lock';
        
        if (Cache::has($lockKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Sincronizzazione già in corso o in coda',
            ], 429);
        }

        // Imposta lock per 2 minuti
        Cache::put($lockKey, true, 120);

        try {
            SyncMuxVideosStatusJob::dispatch();
            
            return response()->json([
                'success' => true,
                'message' => 'Job di sincronizzazione aggiunto alla coda',
            ]);
        } catch (\Exception $e) {
            Cache::forget($lockKey);
            
            return response()->json([
                'success' => false,
                'message' => 'Errore: ' . $e->getMessage(),
            ], 500);
        }
    }
}
