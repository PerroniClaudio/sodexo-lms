<?php

namespace App\Http\Controllers;

use App\Models\CourseEnrollment;
use App\Models\LiveStreamHandRaise;
use App\Models\LiveStreamMessage;
use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Services\TwilioVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LiveStreamController extends Controller
{
    private const PARTICIPANT_STALE_SECONDS = 25;

    public function userPlayer(Request $request, Module $module): View
    {
        $this->abortUnlessLiveModule($module);
        $module->loadMissing('course', 'activeLiveStreamSession');
        $this->ensureUserEnrollment($request, $module);

        if ($this->moduleStartsInFuture($module)) {
            return $this->renderWaitingView($module);
        }

        if ($this->moduleHasEnded($module)) {
            return $this->renderWaitingView($module, __('La diretta è terminata.'), 'ended');
        }

        if ($module->activeLiveStreamSession === null) {
            $latestSession = $module->liveStreamSessions()->latest('started_at')->first();

            if ($latestSession?->status === LiveStreamSession::STATUS_ENDED) {
                return $this->renderWaitingView($module, __('La diretta è terminata.'), 'ended');
            }

            return $this->renderWaitingView($module, __('Il docente non ha ancora avviato la diretta.'));
        }

        return $this->renderPlayerView('user.live-stream.player', $module, LiveStreamParticipant::ROLE_USER);
    }

    public function teacherPlayer(Module $module): View
    {
        $this->abortUnlessLiveModule($module);

        return $this->renderPlayerView('teacher.live-stream.player', $module, LiveStreamParticipant::ROLE_TEACHER);
    }

    public function tutorPlayer(Module $module): View
    {
        $this->abortUnlessLiveModule($module);
        $module->loadMissing('course', 'activeLiveStreamSession');

        if ($this->moduleStartsInFuture($module)) {
            return $this->renderWaitingView($module);
        }

        if ($this->moduleHasEnded($module)) {
            return $this->renderWaitingView($module, __('La diretta è terminata.'), 'ended');
        }

        if ($module->activeLiveStreamSession === null) {
            $latestSession = $module->liveStreamSessions()->latest('started_at')->first();

            if ($latestSession?->status === LiveStreamSession::STATUS_ENDED) {
                return $this->renderWaitingView($module, __('La diretta è terminata.'), 'ended');
            }

            return $this->renderWaitingView($module, __('Il docente non ha ancora avviato la diretta.'));
        }

        return $this->renderPlayerView('user.live-stream.player', $module, LiveStreamParticipant::ROLE_TUTOR);
    }

    public function adminPlayer(Module $module): View
    {
        $this->abortUnlessLiveModule($module);

        return view('admin.live-stream.player', [
            'module' => $module->loadMissing('course'),
            'course' => $module->course,
        ]);
    }

    public function startSession(Request $request, Module $module, TwilioVideoService $twilioVideoService): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $module->loadMissing('activeLiveStreamSession');

        $session = DB::transaction(function () use ($request, $module, $twilioVideoService): LiveStreamSession {
            if ($module->activeLiveStreamSession !== null) {
                Log::info('Live stream start reused active session.', [
                    'module_id' => $module->getKey(),
                    'user_id' => $request->user()->getKey(),
                    'session_id' => $module->activeLiveStreamSession->getKey(),
                ]);

                return $module->activeLiveStreamSession;
            }

            $room = $twilioVideoService->createRoom($module);

            $session = $module->liveStreamSessions()->create([
                'teacher_user_id' => $request->user()->getKey(),
                'twilio_room_sid' => $room['sid'],
                'twilio_room_name' => $room['name'],
                'status' => LiveStreamSession::STATUS_LIVE,
                'started_at' => now(),
            ]);

            Log::info('Live stream session started.', [
                'module_id' => $module->getKey(),
                'user_id' => $request->user()->getKey(),
                'session_id' => $session->getKey(),
                'room_name' => $session->twilio_room_name,
            ]);

            return $session;
        });

        return response()->json([
            'message' => __('Diretta avviata.'),
            'session' => $this->serializeSession($session),
        ]);
    }

    public function endSession(Module $module, TwilioVideoService $twilioVideoService): JsonResponse
    {
        $this->abortUnlessLiveModule($module);

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            return response()->json([
                'message' => __('Nessuna diretta attiva da terminare.'),
            ]);
        }

        $twilioVideoService->completeRoom($session->twilio_room_sid ?? $session->twilio_room_name);

        $session->update([
            'status' => LiveStreamSession::STATUS_ENDED,
            'ended_at' => now(),
        ]);

        $session->participants()
            ->whereNull('left_at')
            ->update([
                'left_at' => now(),
            ]);

        return response()->json([
            'message' => __('Diretta terminata.'),
        ]);
    }

    public function teacherJoin(Request $request, Module $module, TwilioVideoService $twilioVideoService): JsonResponse
    {
        return $this->join($request, $module, LiveStreamParticipant::ROLE_TEACHER, $twilioVideoService);
    }

    public function userJoin(Request $request, Module $module, TwilioVideoService $twilioVideoService): JsonResponse
    {
        return $this->join($request, $module, LiveStreamParticipant::ROLE_USER, $twilioVideoService);
    }

    public function tutorJoin(Request $request, Module $module, TwilioVideoService $twilioVideoService): JsonResponse
    {
        return $this->join($request, $module, LiveStreamParticipant::ROLE_TUTOR, $twilioVideoService);
    }

    public function teacherState(Request $request, Module $module): JsonResponse
    {
        return response()->json($this->buildStatePayload($request, $module, LiveStreamParticipant::ROLE_TEACHER));
    }

    public function userState(Request $request, Module $module): JsonResponse
    {
        $this->ensureUserEnrollment($request, $module);

        return response()->json($this->buildStatePayload($request, $module, LiveStreamParticipant::ROLE_USER));
    }

    public function tutorState(Request $request, Module $module): JsonResponse
    {
        return response()->json($this->buildStatePayload($request, $module, LiveStreamParticipant::ROLE_TUTOR));
    }

    public function teacherPresence(Request $request, Module $module): JsonResponse
    {
        return $this->updatePresence($request, $module, LiveStreamParticipant::ROLE_TEACHER);
    }

    public function userPresence(Request $request, Module $module): JsonResponse
    {
        $this->ensureUserEnrollment($request, $module);

        return $this->updatePresence($request, $module, LiveStreamParticipant::ROLE_USER);
    }

    public function tutorPresence(Request $request, Module $module): JsonResponse
    {
        return $this->updatePresence($request, $module, LiveStreamParticipant::ROLE_TUTOR);
    }

    public function storeTeacherMessage(Request $request, Module $module): JsonResponse
    {
        return $this->storeMessage($request, $module, LiveStreamParticipant::ROLE_TEACHER);
    }

    public function storeUserMessage(Request $request, Module $module): JsonResponse
    {
        $this->ensureUserEnrollment($request, $module);

        return $this->storeMessage($request, $module, LiveStreamParticipant::ROLE_USER);
    }

    public function storeTutorMessage(Request $request, Module $module): JsonResponse
    {
        return $this->storeMessage($request, $module, LiveStreamParticipant::ROLE_TUTOR);
    }

    public function destroyTutorMessage(Request $request, Module $module, LiveStreamMessage $message): JsonResponse
    {
        return $this->destroyMessage($request, $module, $message, LiveStreamParticipant::ROLE_TUTOR);
    }

    public function storeHandRaise(Request $request, Module $module): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureUserEnrollment($request, $module);

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            return response()->json([
                'message' => __('La diretta non è ancora iniziata.'),
            ], Response::HTTP_CONFLICT);
        }

        $participant = $session->participants()
            ->where('user_id', $request->user()->getKey())
            ->where('app_role', LiveStreamParticipant::ROLE_USER)
            ->firstOrFail();

        $handRaise = $session->handRaises()->updateOrCreate(
            [
                'user_id' => $request->user()->getKey(),
                'status' => LiveStreamHandRaise::STATUS_PENDING,
            ],
            [
                'requested_at' => now(),
                'approved_at' => null,
                'resolved_at' => null,
                'approved_by' => null,
            ],
        );

        return response()->json([
            'message' => __('Mano alzata inviata.'),
            'hand_raise' => $this->serializeHandRaise($handRaise),
            'participant_id' => $participant->getKey(),
        ]);
    }

    public function destroyHandRaise(Request $request, Module $module): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureUserEnrollment($request, $module);

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            return response()->json([
                'message' => __('La diretta non è attiva.'),
            ], Response::HTTP_CONFLICT);
        }

        $session->handRaises()
            ->where('user_id', $request->user()->getKey())
            ->where('status', LiveStreamHandRaise::STATUS_PENDING)
            ->update([
                'status' => LiveStreamHandRaise::STATUS_CANCELLED,
                'resolved_at' => now(),
            ]);

        return response()->json([
            'message' => __('Richiesta annullata.'),
        ]);
    }

    public function updateSpeaker(Request $request, Module $module, LiveStreamParticipant $participant): JsonResponse
    {
        $this->abortUnlessLiveModule($module);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $session = $module->activeLiveStreamSession()->first();

        abort_if($session === null, Response::HTTP_CONFLICT, __('La diretta non è attiva.'));
        abort_unless(
            $participant->live_stream_session_id === $session->getKey() && $participant->app_role === LiveStreamParticipant::ROLE_USER,
            Response::HTTP_NOT_FOUND,
        );

        $participant->update([
            'audio_enabled' => (bool) $validated['enabled'],
        ]);

        if ((bool) $validated['enabled']) {
            $session->handRaises()
                ->where('user_id', $participant->user_id)
                ->where('status', LiveStreamHandRaise::STATUS_PENDING)
                ->update([
                    'status' => LiveStreamHandRaise::STATUS_RESOLVED,
                    'approved_at' => now(),
                    'resolved_at' => now(),
                    'approved_by' => $request->user()->getKey(),
                ]);
        }

        return response()->json([
            'message' => $validated['enabled']
                ? __('Discente abilitato al microfono.')
                : __('Microfono discente disattivato.'),
            'participant' => $this->serializeParticipant($participant->fresh(['user'])),
        ]);
    }

    private function join(Request $request, Module $module, string $role, TwilioVideoService $twilioVideoService): JsonResponse
    {
        $this->abortUnlessLiveModule($module);

        if ($role === LiveStreamParticipant::ROLE_USER) {
            $this->ensureUserEnrollment($request, $module);
        }

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            Log::warning('Live stream join attempted without an active session.', [
                'module_id' => $module->getKey(),
                'user_id' => $request->user()->getKey(),
                'role' => $role,
            ]);

            return response()->json([
                'message' => __('La diretta non è attiva.'),
            ], Response::HTTP_CONFLICT);
        }

        $user = $request->user();
        $identity = TwilioVideoService::identityFor($role, $user->getKey());

        $participant = $session->participants()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'app_role' => $role,
                'twilio_identity' => $identity,
                'is_hidden' => $role === LiveStreamParticipant::ROLE_TUTOR,
                'audio_enabled' => $role === LiveStreamParticipant::ROLE_TEACHER,
                'video_enabled' => $role !== LiveStreamParticipant::ROLE_TUTOR,
                'joined_at' => now(),
                'last_seen_at' => now(),
                'left_at' => null,
            ],
        );

        Log::info('Live stream participant joined.', [
            'module_id' => $module->getKey(),
            'session_id' => $session->getKey(),
            'participant_id' => $participant->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'identity' => $identity,
            'room_name' => $session->twilio_room_name,
        ]);

        return response()->json([
            'session_id' => $session->getKey(),
            'twilio_room_name' => $session->twilio_room_name,
            'twilio_token' => $twilioVideoService->createAccessToken($user, $identity, $session->twilio_room_name),
            'participant_identity' => $identity,
            'participant_id' => $participant->getKey(),
            'permissions' => [
                'can_end_session' => $role === LiveStreamParticipant::ROLE_TEACHER,
                'can_raise_hand' => $role === LiveStreamParticipant::ROLE_USER,
                'can_moderate_speakers' => $role === LiveStreamParticipant::ROLE_TEACHER,
                'is_hidden' => $role === LiveStreamParticipant::ROLE_TUTOR,
            ],
        ]);
    }

    private function updatePresence(Request $request, Module $module, string $role): JsonResponse
    {
        $this->abortUnlessLiveModule($module);

        $validated = $request->validate([
            'twilio_participant_sid' => ['nullable', 'string', 'max:255'],
            'audio_enabled' => ['required', 'boolean'],
            'video_enabled' => ['required', 'boolean'],
        ]);

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            Log::warning('Live stream presence update attempted without an active session.', [
                'module_id' => $module->getKey(),
                'user_id' => $request->user()->getKey(),
                'role' => $role,
            ]);

            return response()->json([
                'message' => __('La diretta non è attiva.'),
            ], Response::HTTP_CONFLICT);
        }

        $participant = $session->participants()
            ->where('user_id', $request->user()->getKey())
            ->where('app_role', $role)
            ->first();

        if ($participant === null) {
            Log::warning('Live stream presence update failed because the participant was not found.', [
                'module_id' => $module->getKey(),
                'session_id' => $session->getKey(),
                'user_id' => $request->user()->getKey(),
                'role' => $role,
            ]);

            abort(Response::HTTP_NOT_FOUND, __('Partecipante non trovato.'));
        }

        $updates = [
            'twilio_participant_sid' => $validated['twilio_participant_sid'],
            'video_enabled' => (bool) $validated['video_enabled'],
            'last_seen_at' => now(),
            'left_at' => null,
        ];

        if ($role !== LiveStreamParticipant::ROLE_USER) {
            $updates['audio_enabled'] = (bool) $validated['audio_enabled'];
        }

        $participant->update($updates);

        return response()->json([
            'message' => __('Presenza aggiornata.'),
        ]);
    }

    private function storeMessage(Request $request, Module $module, string $role): JsonResponse
    {
        $this->abortUnlessLiveModule($module);

        $validated = $request->validate([
            'body' => ['required', 'filled', 'string', 'max:1000'],
        ]);

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            return response()->json([
                'message' => __('La diretta non è attiva.'),
            ], Response::HTTP_CONFLICT);
        }

        $participant = $session->participants()
            ->where('user_id', $request->user()->getKey())
            ->where('app_role', $role)
            ->first();

        if ($participant === null) {
            return response()->json([
                'message' => __('Collegati alla diretta prima di usare la chat.'),
            ], Response::HTTP_CONFLICT);
        }

        $message = $session->messages()->create([
            'user_id' => $request->user()->getKey(),
            'app_role' => $role,
            'body' => trim((string) $validated['body']),
            'sent_at' => now(),
        ]);

        $message->load('user');

        return response()->json([
            'message' => __('Messaggio inviato.'),
            'chat_message' => $this->serializeMessage($message),
        ], Response::HTTP_CREATED);
    }

    private function destroyMessage(Request $request, Module $module, LiveStreamMessage $message, string $role): JsonResponse
    {
        $this->abortUnlessLiveModule($module);

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            return response()->json([
                'message' => __('La diretta non è attiva.'),
            ], Response::HTTP_CONFLICT);
        }

        $participant = $session->participants()
            ->where('user_id', $request->user()->getKey())
            ->where('app_role', $role)
            ->first();

        if ($participant === null) {
            return response()->json([
                'message' => __('Collegati alla diretta prima di moderare la chat.'),
            ], Response::HTTP_CONFLICT);
        }

        abort_unless(
            $message->live_stream_session_id === $session->getKey(),
            Response::HTTP_NOT_FOUND,
        );

        $message->delete();

        return response()->json([
            'message' => __('Messaggio rimosso.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStatePayload(Request $request, Module $module, string $role): array
    {
        $this->abortUnlessLiveModule($module);
        $module->loadMissing('course');

        if ($role !== LiveStreamParticipant::ROLE_TEACHER) {
            $availability = $this->buildAudienceAvailabilityPayload($module);

            if ($availability !== null) {
                return $availability;
            }
        }

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            $latestSession = $module->liveStreamSessions()->latest('started_at')->first();

            return [
                'status' => $latestSession?->status === LiveStreamSession::STATUS_ENDED ? 'ended' : 'waiting',
                'message' => $latestSession?->status === LiveStreamSession::STATUS_ENDED
                    ? __('La diretta è terminata.')
                    : __('Il docente non ha ancora avviato la diretta.'),
                'session' => $latestSession ? $this->serializeSession($latestSession) : null,
                'participants' => [],
                'teacher' => null,
                'messages' => [],
                'pending_hand_raises' => [],
                'current_hand_raise' => null,
            ];
        }

        $session->load([
            'participants.user',
            'handRaises.user',
        ]);

        $participants = $session->participants
            ->filter(fn (LiveStreamParticipant $participant): bool => ! $this->participantIsStale($participant))
            ->values();

        $teacher = $participants->firstWhere('app_role', LiveStreamParticipant::ROLE_TEACHER);
        $visibleStudents = $participants
            ->filter(fn (LiveStreamParticipant $participant): bool => $participant->isVisibleStudent())
            ->values();

        $currentHandRaise = $session->handRaises
            ->where('user_id', $request->user()->getKey())
            ->where('status', LiveStreamHandRaise::STATUS_PENDING)
            ->sortByDesc('requested_at')
            ->first();

        $messages = $session->messages()
            ->with('user')
            ->latest('id')
            ->limit(50)
            ->get()
            ->sortBy('id')
            ->values();

        return [
            'status' => 'live',
            'message' => null,
            'session' => $this->serializeSession($session),
            'participants' => $visibleStudents->map(fn (LiveStreamParticipant $participant): array => $this->serializeParticipant($participant))->values()->all(),
            'teacher' => $teacher ? $this->serializeParticipant($teacher) : null,
            'messages' => $messages->map(fn (LiveStreamMessage $message): array => $this->serializeMessage($message))->all(),
            'pending_hand_raises' => $role === LiveStreamParticipant::ROLE_TEACHER
                ? $session->handRaises
                    ->where('status', LiveStreamHandRaise::STATUS_PENDING)
                    ->sortBy('requested_at')
                    ->map(fn (LiveStreamHandRaise $handRaise): array => $this->serializeHandRaise($handRaise))
                    ->values()
                    ->all()
                : [],
            'current_hand_raise' => $currentHandRaise ? $this->serializeHandRaise($currentHandRaise) : null,
        ];
    }

    private function renderPlayerView(string $view, Module $module, string $role): View
    {
        $module->loadMissing('course');

        return view($view, [
            'module' => $module,
            'course' => $module->course,
            'liveStreamConfig' => $this->buildViewConfig($module, $role),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewConfig(Module $module, string $role): array
    {
        $routePrefix = match ($role) {
            LiveStreamParticipant::ROLE_TEACHER => 'teacher',
            LiveStreamParticipant::ROLE_TUTOR => 'tutor',
            default => 'user',
        };

        return [
            'role' => $role,
            'moduleId' => $module->getKey(),
            'pollIntervals' => [
                'state' => 5000,
                'presence' => 10000,
            ],
            'routes' => [
                'join' => route($routePrefix.'.live-stream.join', $module),
                'state' => route($routePrefix.'.live-stream.state', $module),
                'presence' => route($routePrefix.'.live-stream.presence', $module),
                'chat' => in_array($role, [LiveStreamParticipant::ROLE_TEACHER, LiveStreamParticipant::ROLE_TUTOR, LiveStreamParticipant::ROLE_USER], true)
                    ? route($routePrefix.'.live-stream.messages.store', $module)
                    : null,
                'deleteMessageTemplate' => $role === LiveStreamParticipant::ROLE_TUTOR
                    ? route('tutor.live-stream.messages.destroy', [$module, '__MESSAGE__'])
                    : null,
                'startSession' => $role === LiveStreamParticipant::ROLE_TEACHER
                    ? route('teacher.live-stream.session.start', $module)
                    : null,
                'endSession' => $role === LiveStreamParticipant::ROLE_TEACHER
                    ? route('teacher.live-stream.session.end', $module)
                    : null,
                'handRaise' => $role === LiveStreamParticipant::ROLE_USER
                    ? route('user.live-stream.hand-raises.store', $module)
                    : null,
                'cancelHandRaise' => $role === LiveStreamParticipant::ROLE_USER
                    ? route('user.live-stream.hand-raises.destroy', $module)
                    : null,
                'speakerTemplate' => $role === LiveStreamParticipant::ROLE_TEACHER
                    ? route('teacher.live-stream.participants.speaker', [$module, '__PARTICIPANT__'])
                    : null,
            ],
            'capabilities' => [
                'canEndSession' => $role === LiveStreamParticipant::ROLE_TEACHER,
                'canRaiseHand' => $role === LiveStreamParticipant::ROLE_USER,
                'canModerateChat' => $role === LiveStreamParticipant::ROLE_TUTOR,
                'canModerateSpeakers' => $role === LiveStreamParticipant::ROLE_TEACHER,
                'hiddenParticipant' => $role === LiveStreamParticipant::ROLE_TUTOR,
            ],
        ];
    }

    private function renderWaitingView(Module $module, ?string $message = null, string $state = 'waiting'): View
    {
        $module->loadMissing('course');

        return view('user.live-stream.waiting', [
            'module' => $module,
            'course' => $module->course,
            'waitingState' => $state,
            'waitingMessage' => $message,
        ]);
    }

    private function ensureUserEnrollment(Request $request, Module $module): void
    {
        $module->loadMissing('course');

        $isEnrolled = CourseEnrollment::query()
            ->where('user_id', $request->user()->getKey())
            ->where('course_id', $module->course?->getKey())
            ->whereNull('deleted_at')
            ->exists();

        abort_unless($isEnrolled, Response::HTTP_FORBIDDEN);
    }

    private function abortUnlessLiveModule(Module $module): void
    {
        abort_unless($module->type === 'live', Response::HTTP_NOT_FOUND);
    }

    private function moduleStartsInFuture(Module $module): bool
    {
        return $module->appointment_start_time !== null && now()->lt($module->appointment_start_time);
    }

    private function moduleHasEnded(Module $module): bool
    {
        return $module->appointment_end_time !== null && now()->gte($module->appointment_end_time);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildAudienceAvailabilityPayload(Module $module): ?array
    {
        if ($this->moduleStartsInFuture($module)) {
            return $this->emptyStatePayload('waiting', __('La diretta comincia all\'orario stabilito.'));
        }

        $latestSession = $module->liveStreamSessions()->latest('started_at')->first();

        if ($this->moduleHasEnded($module) || $latestSession?->status === LiveStreamSession::STATUS_ENDED) {
            return $this->emptyStatePayload('ended', __('La diretta è terminata.'));
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStatePayload(string $status, string $message): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'session' => null,
            'participants' => [],
            'teacher' => null,
            'messages' => [],
            'pending_hand_raises' => [],
            'current_hand_raise' => null,
        ];
    }

    private function participantIsStale(LiveStreamParticipant $participant): bool
    {
        if ($participant->left_at !== null) {
            return true;
        }

        if ($participant->last_seen_at === null) {
            return false;
        }

        return $participant->last_seen_at->lt(now()->subSeconds(self::PARTICIPANT_STALE_SECONDS));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeParticipant(LiveStreamParticipant $participant): array
    {
        $user = $participant->user;
        $fullName = $user?->full_name ?? __('Utente sconosciuto');

        return [
            'id' => $participant->getKey(),
            'user_id' => $participant->user_id,
            'name' => $fullName,
            'initials' => $this->initialsFor($fullName),
            'app_role' => $participant->app_role,
            'twilio_identity' => $participant->twilio_identity,
            'twilio_participant_sid' => $participant->twilio_participant_sid,
            'is_hidden' => $participant->is_hidden,
            'audio_enabled' => $participant->audio_enabled,
            'video_enabled' => $participant->video_enabled,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHandRaise(LiveStreamHandRaise $handRaise): array
    {
        return [
            'id' => $handRaise->getKey(),
            'user_id' => $handRaise->user_id,
            'name' => $handRaise->user?->full_name ?? __('Utente sconosciuto'),
            'requested_at' => $handRaise->requested_at?->toIso8601String(),
            'status' => $handRaise->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(LiveStreamMessage $message): array
    {
        $fullName = $message->user?->full_name ?? __('Utente sconosciuto');

        return [
            'id' => $message->getKey(),
            'user_id' => $message->user_id,
            'name' => $fullName,
            'initials' => $this->initialsFor($fullName),
            'app_role' => $message->app_role,
            'body' => $message->body,
            'sent_at' => $message->sent_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(LiveStreamSession $session): array
    {
        return [
            'id' => $session->getKey(),
            'status' => $session->status,
            'started_at' => $session->started_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'twilio_room_name' => $session->twilio_room_name,
            'twilio_room_sid' => $session->twilio_room_sid,
        ];
    }

    private function initialsFor(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
