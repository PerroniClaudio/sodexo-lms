<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Response;
use Illuminate\View\View;

class LiveStreamController extends Controller
{
    public function userPlayer(Module $module): View
    {
        abort_unless($module->type === 'live', Response::HTTP_NOT_FOUND);

        $module->loadMissing('course');

        if ($module->appointment_start_time !== null && now()->lt($module->appointment_start_time)) {
            return view('user.live-stream.waiting', [
                'module' => $module,
                'course' => $module->course,
            ]);
        }

        return view('user.live-stream.player', [
            'module' => $module,
            'course' => $module->course,
        ]);
    }

    public function teacherPlayer(Module $module): View
    {
        return $this->renderPlayerView('teacher.live-stream.player', $module);
    }

    public function tutorPlayer(Module $module): View
    {
        return $this->renderPlayerView('tutor.live-stream.player', $module);
    }

    public function adminPlayer(Module $module): View
    {
        return $this->renderPlayerView('admin.live-stream.player', $module);
    }

    private function renderPlayerView(string $view, Module $module): View
    {
        abort_unless($module->type === 'live', Response::HTTP_NOT_FOUND);

        $module->loadMissing('course');

        return view($view, [
            'module' => $module,
            'course' => $module->course,
        ]);
    }
}
