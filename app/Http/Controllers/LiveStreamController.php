<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LiveStreamController extends Controller
{
    public function userPlayer(): View
    {
        return view('user.live-stream.player');
    }

    public function teacherPlayer(): View
    {
        return view('teacher.live-stream.player');
    }

    public function tutorPlayer(): View
    {
        return view('tutor.live-stream.player');
    }

    public function adminPlayer(): View
    {
        return view('admin.live-stream.player');
    }
}
