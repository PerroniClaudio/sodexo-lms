<?php

namespace App\Http\Controllers;

use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\LiveStreamAttendanceMinute;
use App\Models\LiveStreamDocument;
use App\Models\LiveStreamHandRaise;
use App\Models\LiveStreamMessage;
use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamPoll;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Services\TwilioVideoService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveStreamController extends Controller
{
    private const DOCUMENT_DISK = 'local';

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

    public function teacherPlayer(Request $request, Module $module): View
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureTeacherEnrollment($request, $module);

        return $this->renderPlayerView('teacher.live-stream.player', $module, LiveStreamParticipant::ROLE_TEACHER);
    }

    public function tutorPlayer(Request $request, Module $module): View
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureTutorEnrollment($request, $module);
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
        $this->ensureTeacherEnrollment($request, $module);
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

    public function endSession(Request $request, Module $module, TwilioVideoService $twilioVideoService): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureTeacherEnrollment($request, $module);

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

        $session->polls()
            ->where('status', LiveStreamPoll::STATUS_OPEN)
            ->update([
                'status' => LiveStreamPoll::STATUS_CLOSED,
                'closed_at' => now(),
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
        $this->ensureTeacherEnrollment($request, $module);

        return response()->json($this->buildStatePayload($request, $module, LiveStreamParticipant::ROLE_TEACHER));
    }

    public function userState(Request $request, Module $module): JsonResponse
    {
        $this->ensureUserEnrollment($request, $module);

        return response()->json($this->buildStatePayload($request, $module, LiveStreamParticipant::ROLE_USER));
    }

    public function tutorState(Request $request, Module $module): JsonResponse
    {
        $this->ensureTutorEnrollment($request, $module);

        return response()->json($this->buildStatePayload($request, $module, LiveStreamParticipant::ROLE_TUTOR));
    }

    public function teacherPresence(Request $request, Module $module): JsonResponse
    {
        $this->ensureTeacherEnrollment($request, $module);

        return $this->updatePresence($request, $module, LiveStreamParticipant::ROLE_TEACHER);
    }

    public function userPresence(Request $request, Module $module): JsonResponse
    {
        $this->ensureUserEnrollment($request, $module);

        return $this->updatePresence($request, $module, LiveStreamParticipant::ROLE_USER);
    }

    public function tutorPresence(Request $request, Module $module): JsonResponse
    {
        $this->ensureTutorEnrollment($request, $module);

        return $this->updatePresence($request, $module, LiveStreamParticipant::ROLE_TUTOR);
    }

    public function storeTeacherMessage(Request $request, Module $module): JsonResponse
    {
        $this->ensureTeacherEnrollment($request, $module);

        return $this->storeMessage($request, $module, LiveStreamParticipant::ROLE_TEACHER);
    }

    public function storeUserMessage(Request $request, Module $module): JsonResponse
    {
        $this->ensureUserEnrollment($request, $module);

        return $this->storeMessage($request, $module, LiveStreamParticipant::ROLE_USER);
    }

    public function storeTutorMessage(Request $request, Module $module): JsonResponse
    {
        $this->ensureTutorEnrollment($request, $module);

        return $this->storeMessage($request, $module, LiveStreamParticipant::ROLE_TUTOR);
    }

    public function storeTeacherPoll(Request $request, Module $module): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureTeacherEnrollment($request, $module);

        $validated = $this->validatePollPayload($request);
        $session = $module->activeLiveStreamSession()->first();

        if ($session === null) {
            return response()->json([
                'message' => __('La diretta non è attiva.'),
            ], Response::HTTP_CONFLICT);
        }

        $poll = DB::transaction(function () use ($request, $session, $validated): LiveStreamPoll {
            $session->polls()
                ->where('status', LiveStreamPoll::STATUS_OPEN)
                ->update([
                    'status' => LiveStreamPoll::STATUS_CLOSED,
                    'closed_at' => now(),
                ]);

            return $session->polls()->create([
                'user_id' => $request->user()->getKey(),
                'question' => $validated['question'],
                'options' => $validated['options'],
                'status' => LiveStreamPoll::STATUS_OPEN,
                'published_at' => now(),
            ]);
        });

        $poll->load('responses');

        return response()->json([
            'message' => __('Sondaggio pubblicato.'),
            'poll' => $this->serializePoll($poll, $request->user()->getKey()),
        ], Response::HTTP_CREATED);
    }

    public function closeTeacherPoll(Request $request, Module $module, LiveStreamPoll $poll): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureTeacherEnrollment($request, $module);

        $session = $module->activeLiveStreamSession()->first();

        abort_if($session === null, Response::HTTP_CONFLICT, __('La diretta non è attiva.'));
        abort_unless($poll->live_stream_session_id === $session->getKey(), Response::HTTP_NOT_FOUND);

        if (! $poll->isOpen()) {
            return response()->json([
                'message' => __('Il sondaggio è già chiuso.'),
                'poll' => $this->serializePoll($poll->load('responses'), $request->user()->getKey()),
            ]);
        }

        $poll->update([
            'status' => LiveStreamPoll::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => __('Invio risposte terminato.'),
            'poll' => $this->serializePoll($poll->fresh('responses'), $request->user()->getKey()),
        ]);
    }

    public function storeUserPollResponse(Request $request, Module $module, LiveStreamPoll $poll): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureUserEnrollment($request, $module);

        $session = $module->activeLiveStreamSession()->first();

        if ($session === null || $poll->live_stream_session_id !== $session->getKey()) {
            return response()->json([
                'message' => __('Il sondaggio non è disponibile.'),
            ], Response::HTTP_CONFLICT);
        }

        if (! $poll->isOpen()) {
            return response()->json([
                'message' => __('Il sondaggio è chiuso.'),
            ], Response::HTTP_CONFLICT);
        }

        $participant = $session->participants()
            ->where('user_id', $request->user()->getKey())
            ->where('app_role', LiveStreamParticipant::ROLE_USER)
            ->first();

        if ($participant === null) {
            return response()->json([
                'message' => __('Collegati alla diretta prima di rispondere al sondaggio.'),
            ], Response::HTTP_CONFLICT);
        }

        $validated = $request->validate([
            'answer_index' => [
                'required',
                'integer',
                Rule::in(array_keys($poll->options ?? [])),
            ],
        ]);

        $alreadyAnswered = $poll->responses()
            ->where('user_id', $request->user()->getKey())
            ->exists();

        if ($alreadyAnswered) {
            return response()->json([
                'message' => __('Hai già risposto a questo sondaggio.'),
            ], Response::HTTP_CONFLICT);
        }

        $response = $poll->responses()->create([
            'user_id' => $request->user()->getKey(),
            'answer_index' => (int) $validated['answer_index'],
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => __('Risposta inviata.'),
            'poll_response' => [
                'id' => $response->getKey(),
                'answer_index' => $response->answer_index,
            ],
        ], Response::HTTP_CREATED);
    }

    public function destroyTutorMessage(Request $request, Module $module, LiveStreamMessage $message): JsonResponse
    {
        $this->ensureTutorEnrollment($request, $module);

        return $this->destroyMessage($request, $module, $message, LiveStreamParticipant::ROLE_TUTOR);
    }

    public function storeTeacherDocument(Request $request, Module $module): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureTeacherEnrollment($request, $module);

        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
        ]);

        $file = $validated['document'];
        $storedPath = $file->storeAs(
            'live-stream-documents/'.$module->getKey(),
            Str::uuid().'.pdf',
            self::DOCUMENT_DISK,
        );

        $document = $module->liveStreamDocuments()->create([
            'user_id' => $request->user()->getKey(),
            'disk' => self::DOCUMENT_DISK,
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType() ?: 'application/pdf',
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_at' => now(),
        ]);

        $document->load('user');

        return response()->json([
            'message' => __('PDF caricato con successo.'),
            'document' => $this->serializeDocument($document, LiveStreamParticipant::ROLE_TEACHER),
        ], Response::HTTP_CREATED);
    }

    public function destroyTeacherDocument(Request $request, Module $module, LiveStreamDocument $document): JsonResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->ensureTeacherEnrollment($request, $module);
        $this->abortUnlessDocumentBelongsToModule($module, $document);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        return response()->json([
            'message' => __('PDF rimosso con successo.'),
        ]);
    }

    public function downloadTeacherDocument(Request $request, Module $module, LiveStreamDocument $document): StreamedResponse
    {
        $this->ensureTeacherEnrollment($request, $module);

        return $this->downloadDocument($module, $document);
    }

    public function downloadUserDocument(Request $request, Module $module, LiveStreamDocument $document): StreamedResponse
    {
        $this->ensureUserEnrollment($request, $module);

        return $this->downloadDocument($module, $document);
    }

    public function downloadTutorDocument(Module $module, LiveStreamDocument $document): StreamedResponse
    {
        $this->ensureTutorEnrollment(request(), $module);

        return $this->downloadDocument($module, $document);
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
        $this->ensureTeacherEnrollment($request, $module);

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

        if ($role === LiveStreamParticipant::ROLE_TEACHER) {
            $this->ensureTeacherEnrollment($request, $module);
        }

        if ($role === LiveStreamParticipant::ROLE_TUTOR) {
            $this->ensureTutorEnrollment($request, $module);
        }

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

        $serverTimestamp = now();

        $updates = [
            'twilio_participant_sid' => $validated['twilio_participant_sid'],
            'video_enabled' => (bool) $validated['video_enabled'],
            'last_seen_at' => $serverTimestamp,
            'left_at' => null,
        ];

        if ($role !== LiveStreamParticipant::ROLE_USER) {
            $updates['audio_enabled'] = (bool) $validated['audio_enabled'];
        }

        $participant->update($updates);
        $this->recordAttendanceMinute($session, $module, $request->user()->getKey(), $serverTimestamp);

        return response()->json([
            'message' => __('Presenza aggiornata.'),
        ]);
    }

    private function recordAttendanceMinute(
        LiveStreamSession $session,
        Module $module,
        int $userId,
        Carbon $serverTimestamp,
    ): void {
        $minuteTimestamp = $serverTimestamp->copy()->startOfMinute();

        $attendanceMinute = LiveStreamAttendanceMinute::query()->firstOrNew([
            'live_stream_session_id' => $session->getKey(),
            'user_id' => $userId,
            'minute_at' => $minuteTimestamp,
        ]);

        if (! $attendanceMinute->exists) {
            $attendanceMinute->module_id = $module->getKey();
            $attendanceMinute->first_seen_at = $serverTimestamp;
            $attendanceMinute->last_seen_at = $serverTimestamp;
            $attendanceMinute->heartbeat_count = 1;
            $attendanceMinute->save();

            return;
        }

        $attendanceMinute->module_id = $module->getKey();
        $attendanceMinute->last_seen_at = $serverTimestamp;
        $attendanceMinute->heartbeat_count++;
        $attendanceMinute->save();
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
            $availability = $this->buildAudienceAvailabilityPayload($module, $role);

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
                'documents' => $this->serializeDocuments($module, $role),
                'pending_hand_raises' => [],
                'current_hand_raise' => null,
                'polls' => [],
                'active_poll' => null,
            ];
        }

        $session->load([
            'participants.user',
            'handRaises.user',
            'polls.responses',
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
            'documents' => $this->serializeDocuments($module, $role),
            'pending_hand_raises' => $role === LiveStreamParticipant::ROLE_TEACHER
                ? $session->handRaises
                    ->where('status', LiveStreamHandRaise::STATUS_PENDING)
                    ->sortBy('requested_at')
                    ->map(fn (LiveStreamHandRaise $handRaise): array => $this->serializeHandRaise($handRaise))
                    ->values()
                    ->all()
                : [],
            'current_hand_raise' => $currentHandRaise ? $this->serializeHandRaise($currentHandRaise) : null,
            'polls' => $role === LiveStreamParticipant::ROLE_TEACHER
                ? $session->polls
                    ->sortByDesc('id')
                    ->map(fn (LiveStreamPoll $poll): array => $this->serializePoll($poll, $request->user()->getKey()))
                    ->values()
                    ->all()
                : [],
            'active_poll' => $role === LiveStreamParticipant::ROLE_USER
                ? $this->serializeActivePoll($session, $request->user()->getKey())
                : null,
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
                'presence' => 30000,
            ],
            'routes' => [
                'join' => route($routePrefix.'.live-stream.join', $module),
                'state' => route($routePrefix.'.live-stream.state', $module),
                'presence' => route($routePrefix.'.live-stream.presence', $module),
                'chat' => in_array($role, [LiveStreamParticipant::ROLE_TEACHER, LiveStreamParticipant::ROLE_TUTOR, LiveStreamParticipant::ROLE_USER], true)
                    ? route($routePrefix.'.live-stream.messages.store', $module)
                    : null,
                'pollsStore' => $role === LiveStreamParticipant::ROLE_TEACHER
                    ? route('teacher.live-stream.polls.store', $module)
                    : null,
                'closePollTemplate' => $role === LiveStreamParticipant::ROLE_TEACHER
                    ? route('teacher.live-stream.polls.close', [$module, '__POLL__'])
                    : null,
                'pollResponseTemplate' => $role === LiveStreamParticipant::ROLE_USER
                    ? route('user.live-stream.polls.responses.store', [$module, '__POLL__'])
                    : null,
                'uploadDocument' => $role === LiveStreamParticipant::ROLE_TEACHER
                    ? route('teacher.live-stream.documents.store', $module)
                    : null,
                'deleteDocumentTemplate' => $role === LiveStreamParticipant::ROLE_TEACHER
                    ? route('teacher.live-stream.documents.destroy', [$module, '__DOCUMENT__'])
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
                'canManageDocuments' => $role === LiveStreamParticipant::ROLE_TEACHER,
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

    private function ensureTeacherEnrollment(Request $request, Module $module): void
    {
        if ($request->user()?->hasRole('superadmin')) {
            return;
        }

        $module->loadMissing('course');

        $isAssigned = CourseTeacherEnrollment::query()
            ->where('user_id', $request->user()->getKey())
            ->where('course_id', $module->course?->getKey())
            ->whereNull('deleted_at')
            ->exists();

        abort_unless($isAssigned, Response::HTTP_FORBIDDEN);
    }

    private function ensureTutorEnrollment(Request $request, Module $module): void
    {
        if ($request->user()?->hasRole('superadmin')) {
            return;
        }

        $module->loadMissing('course');

        $isAssigned = CourseTutorEnrollment::query()
            ->where('user_id', $request->user()->getKey())
            ->where('course_id', $module->course?->getKey())
            ->whereNull('deleted_at')
            ->exists();

        abort_unless($isAssigned, Response::HTTP_FORBIDDEN);
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
    private function buildAudienceAvailabilityPayload(Module $module, string $role): ?array
    {
        $documents = $this->serializeDocuments($module, $role);

        if ($this->moduleStartsInFuture($module)) {
            return $this->emptyStatePayload('waiting', __('La diretta comincia all\'orario stabilito.'), $documents);
        }

        $latestSession = $module->liveStreamSessions()->latest('started_at')->first();

        if ($this->moduleHasEnded($module) || $latestSession?->status === LiveStreamSession::STATUS_ENDED) {
            return $this->emptyStatePayload('ended', __('La diretta è terminata.'), $documents);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStatePayload(string $status, string $message, array $documents = []): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'session' => null,
            'participants' => [],
            'teacher' => null,
            'messages' => [],
            'documents' => $documents,
            'pending_hand_raises' => [],
            'current_hand_raise' => null,
            'polls' => [],
            'active_poll' => null,
        ];
    }

    private function downloadDocument(Module $module, LiveStreamDocument $document): StreamedResponse
    {
        $this->abortUnlessLiveModule($module);
        $this->abortUnlessDocumentBelongsToModule($module, $document);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), Response::HTTP_NOT_FOUND);

        return $disk->download(
            $document->path,
            $this->downloadNameFor($document),
            [
                'Content-Type' => $document->mime_type,
            ],
        );
    }

    private function abortUnlessDocumentBelongsToModule(Module $module, LiveStreamDocument $document): void
    {
        abort_unless($document->module_id === $module->getKey(), Response::HTTP_NOT_FOUND);
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
     * @return array{question: string, options: array<int, string>}
     */
    private function validatePollPayload(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'question' => ['required', 'string', 'max:1000'],
            'options' => ['required', 'array', 'max:4'],
            'options.*' => ['nullable', 'string', 'max:255'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $question = trim((string) $request->input('question', ''));
            $options = collect($request->input('options', []))
                ->map(fn (mixed $option): string => trim((string) $option))
                ->filter()
                ->values();

            if ($question === '') {
                $validator->errors()->add('question', __('Inserisci la domanda del sondaggio.'));
            }

            if ($options->count() < 2) {
                $validator->errors()->add('options', __('Inserisci almeno due risposte.'));
            }

            if ($options->count() > 4) {
                $validator->errors()->add('options', __('Puoi inserire al massimo quattro risposte.'));
            }

            if ($options->unique()->count() !== $options->count()) {
                $validator->errors()->add('options', __('Le risposte devono essere diverse tra loro.'));
            }
        });

        $validated = $validator->validate();

        return [
            'question' => trim((string) $validated['question']),
            'options' => collect($validated['options'])
                ->map(fn (mixed $option): string => trim((string) $option))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePoll(LiveStreamPoll $poll, int $currentUserId): array
    {
        $poll->loadMissing('responses');

        $responses = $poll->responses;
        $responseCounts = $responses->countBy('answer_index');
        $totalResponses = $responses->count();
        $currentResponse = $responses->firstWhere('user_id', $currentUserId);

        return [
            'id' => $poll->getKey(),
            'question' => $poll->question,
            'status' => $poll->status,
            'is_open' => $poll->isOpen(),
            'published_at' => $poll->published_at?->toIso8601String(),
            'closed_at' => $poll->closed_at?->toIso8601String(),
            'total_responses' => $totalResponses,
            'current_answer_index' => $currentResponse?->answer_index,
            'options' => collect($poll->options ?? [])
                ->values()
                ->map(function (mixed $label, int $index) use ($responseCounts, $totalResponses, $currentResponse): array {
                    $responsesCount = (int) $responseCounts->get($index, 0);
                    $percentage = $totalResponses > 0
                        ? round(($responsesCount / $totalResponses) * 100, 1)
                        : 0;

                    return [
                        'index' => $index,
                        'label' => (string) $label,
                        'responses_count' => $responsesCount,
                        'percentage' => $percentage,
                        'is_selected' => $currentResponse?->answer_index === $index,
                    ];
                })
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeActivePoll(LiveStreamSession $session, int $currentUserId): ?array
    {
        $session->loadMissing(['participants', 'polls.responses']);

        $participant = $session->participants
            ->first(fn (LiveStreamParticipant $participant): bool => $participant->user_id === $currentUserId
                && $participant->app_role === LiveStreamParticipant::ROLE_USER
                && ! $this->participantIsStale($participant));

        if ($participant === null) {
            return null;
        }

        /** @var LiveStreamPoll|null $poll */
        $poll = $session->polls
            ->sortByDesc('id')
            ->first(fn (LiveStreamPoll $poll): bool => $poll->isOpen());

        if ($poll === null) {
            return null;
        }

        $alreadyAnswered = $poll->responses->contains(fn ($response): bool => $response->user_id === $currentUserId);

        if ($alreadyAnswered) {
            return null;
        }

        return [
            'id' => $poll->getKey(),
            'question' => $poll->question,
            'status' => $poll->status,
            'published_at' => $poll->published_at?->toIso8601String(),
            'options' => collect($poll->options ?? [])
                ->values()
                ->map(fn (mixed $label, int $index): array => [
                    'index' => $index,
                    'label' => (string) $label,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeDocuments(Module $module, string $role): array
    {
        return $module->liveStreamDocuments()
            ->with('user')
            ->latest('uploaded_at')
            ->latest('id')
            ->get()
            ->map(fn (LiveStreamDocument $document): array => $this->serializeDocument($document, $role))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(LiveStreamDocument $document, string $role): array
    {
        return [
            'id' => $document->getKey(),
            'name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'uploaded_at' => $document->uploaded_at?->toIso8601String(),
            'uploaded_by' => $document->user?->full_name ?? __('Utente sconosciuto'),
            'download_url' => route($this->routePrefixForRole($role).'.live-stream.documents.download', [$document->module_id, $document]),
        ];
    }

    private function routePrefixForRole(string $role): string
    {
        return match ($role) {
            LiveStreamParticipant::ROLE_TEACHER => 'teacher',
            LiveStreamParticipant::ROLE_TUTOR => 'tutor',
            default => 'user',
        };
    }

    private function downloadNameFor(LiveStreamDocument $document): string
    {
        $extension = Str::of($document->original_name)->afterLast('.')->lower()->value() ?: 'pdf';
        $baseName = pathinfo($document->original_name, PATHINFO_FILENAME);
        $asciiName = Str::of(Str::ascii($baseName))
            ->replaceMatches('/[^A-Za-z0-9\-_]+/', '-')
            ->trim('-')
            ->value();

        if ($asciiName === '') {
            $asciiName = 'materiale-live-'.$document->getKey();
        }

        return $asciiName.'.'.$extension;
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
