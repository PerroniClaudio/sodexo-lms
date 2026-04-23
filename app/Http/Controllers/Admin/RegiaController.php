<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamSession;
use App\Models\Module;
use Illuminate\Contracts\View\View;

class RegiaController extends Controller
{
    public function index(): View
    {
        $now = now();

        $modules = Module::query()
            ->with('course')
            ->where('type', 'live')
            ->where('is_live_teacher', false)
            ->whereHas('course')
            ->whereBetween('appointment_start_time', [$now->copy()->startOfDay(), $now->copy()->endOfDay()])
            ->where(function ($query) use ($now) {
                $query->whereNull('appointment_end_time')
                    ->orWhere('appointment_end_time', '>=', $now);
            })
            ->orderBy('appointment_start_time')
            ->get();

        return view('admin.regia.index', [
            'modules' => $modules,
        ]);
    }

    public function show(Module $module): View
    {
        abort_unless($module->type === 'live' && ! $module->is_live_teacher, 404);

        $module->loadMissing('course', 'activeLiveStreamSession');

        return view('admin.regia.player', [
            'module' => $module,
            'course' => $module->course,
            'liveStreamConfig' => $this->buildLiveStreamConfig($module),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLiveStreamConfig(Module $module): array
    {
        $session = $module->activeLiveStreamSession;

        return [
            'role' => LiveStreamParticipant::ROLE_ADMIN,
            'moduleId' => $module->getKey(),
            'streamMode' => 'mux_regia',
            'pollIntervals' => [
                'state' => 5000,
                'presence' => 30000,
            ],
            'mux' => [
                'liveStreamId' => $module->mux_live_stream_id,
                'playbackId' => $module->mux_playback_id,
                'playbackUrl' => $module->mux_playback_id ? sprintf('https://player.mux.com/%s', $module->mux_playback_id) : null,
                'streamKey' => $module->mux_stream_key,
                'ingestUrl' => $module->mux_ingest_url,
                'status' => $session?->mux_broadcast_status ?? LiveStreamSession::BROADCAST_STATUS_IDLE,
                'isLive' => $session?->isBroadcastLive() ?? false,
            ],
            'routes' => [
                'join' => route('admin.regia.join', $module),
                'state' => route('admin.regia.state', $module),
                'presence' => route('admin.regia.presence', $module),
                'chat' => route('admin.regia.messages.store', $module),
                'pollsStore' => route('admin.regia.polls.store', $module),
                'closePollTemplate' => route('admin.regia.polls.close', [$module, '__POLL__']),
                'uploadDocument' => route('admin.regia.documents.store', $module),
                'deleteDocumentTemplate' => route('admin.regia.documents.destroy', [$module, '__DOCUMENT__']),
                'startSession' => route('admin.regia.session.start', $module),
                'endSession' => route('admin.regia.session.end', $module),
                'speakerTemplate' => route('admin.regia.participants.speaker', [$module, '__PARTICIPANT__']),
                'regiaStart' => route('admin.regia.session.start', $module),
                'regiaEnd' => route('admin.regia.session.end', $module),
                'regiaState' => route('admin.regia.state', $module),
            ],
            'capabilities' => [
                'canEndSession' => true,
                'canRaiseHand' => false,
                'canModerateChat' => false,
                'canModerateSpeakers' => true,
                'canManageDocuments' => true,
                'canManageBroadcast' => true,
                'hiddenParticipant' => true,
                'requiresPreview' => false,
            ],
        ];
    }
}
