<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Support\UserGeographyMapper;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller solo per gestione utenti da frontend (Blade, no API)
 * Gestione del profilo utente autenticato
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserGeographyMapper $userGeographyMapper,
    ) {}

    /**
     * Mostra la pagina di modifica del proprio profilo utente
     */
    public function editOwnProfile(): View
    {
        $user = $this->authUser();
        $userRole = $user->roles()->first()?->name;

        // Dato che per ora possono fare le stesse modifiche nel profilo, manteniamo la stess view.
        return view('user.profile.edit', compact('user'));
    }

    /**
     * Aggiorna i dati personali dell'utente autenticato (profilo proprio)
     */
    public function updateOwnProfile(Request $request): RedirectResponse
    {
        $user = $this->authUser();
        $userRole = $user->roles()->first()?->name;

        $validated = $request->validate([
            'phone_prefix' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:1'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ]);

        // Conversione geografica come in update
        $data = $this->userGeographyMapper->toHomeIds($validated);

        $user->update($data);

        // Reindirizzamento in base al ruolo
        return redirect()->route($userRole.'.profile.edit')->with('status', __('Profilo aggiornato con successo!'));
    }

    public function coursesStats(): JsonResponse
    {
        $user = $this->authUser();
        $enrollments = $user->courseEnrollments()
            ->with('course:id,title')
            ->orderByDesc('last_accessed_at')
            ->orderByDesc('assigned_at')
            ->get([
                'id',
                'user_id',
                'course_id',
                'completion_percentage',
                'assigned_at',
                'last_accessed_at',
            ]);

        $overallProgress = (int) round($enrollments->avg('completion_percentage') ?? 0);

        return response()->json([
            'overall_progress' => $overallProgress,
            'remaining_progress' => max(0, 100 - $overallProgress),
            'courses' => $enrollments
                ->take(4)
                ->map(fn (CourseEnrollment $enrollment): array => [
                    'title' => $enrollment->course?->title ?? __('Corso senza titolo'),
                    'progress' => (int) ($enrollment->completion_percentage ?? 0),
                ])
                ->values(),
            'weekly_activity' => $this->weeklyActivityFor($user),
        ]);
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     hours: array<int, float>
     * }
     */
    private function weeklyActivityFor(User $user): array
    {
        $today = CarbonImmutable::today();
        $startDate = $today->subDays(6)->startOfDay();

        $hoursByDate = ModuleProgress::query()
            ->join('course_user', 'course_user.id', '=', 'module_user.course_user_id')
            ->where('course_user.user_id', $user->getKey())
            ->whereNotNull('module_user.last_accessed_at')
            ->where('module_user.last_accessed_at', '>=', $startDate)
            ->selectRaw('DATE(module_user.last_accessed_at) as activity_date')
            ->selectRaw('SUM(module_user.time_spent_seconds) as total_time_spent_seconds')
            ->groupBy('activity_date')
            ->pluck('total_time_spent_seconds', 'activity_date');

        $days = collect(range(0, 6))
            ->map(fn (int $offset): CarbonImmutable => $startDate->addDays($offset));

        return [
            'labels' => $days
                ->map(fn (CarbonImmutable $day): string => ucfirst($day->locale(app()->getLocale())->translatedFormat('D')))
                ->all(),
            'hours' => $days
                ->map(function (CarbonImmutable $day) use ($hoursByDate): float {
                    $seconds = (int) ($hoursByDate[$day->toDateString()] ?? 0);

                    return round($seconds / 3600, 2);
                })
                ->all(),
        ];
    }

    private function authUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
