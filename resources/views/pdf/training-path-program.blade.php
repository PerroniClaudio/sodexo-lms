<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Programma formativo') }}</title>
    <style>
        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        h1, h2, h3 {
            margin: 0;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        h2 {
            border-bottom: 1px solid #d1d5db;
            font-size: 16px;
            margin-bottom: 10px;
            padding-bottom: 4px;
        }

        h3 {
            font-size: 14px;
            margin-bottom: 8px;
        }

        p {
            margin: 0 0 8px;
        }

        .section {
            margin-bottom: 24px;
        }

        .muted {
            color: #6b7280;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
        }

        .grid td {
            border-bottom: 1px solid #e5e7eb;
            padding: 6px 0;
            vertical-align: top;
        }

        .label {
            color: #374151;
            font-weight: 700;
            width: 180px;
            padding-right: 16px;
        }

        .course-card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            margin-bottom: 18px;
            padding: 14px;
        }

        .course-meta {
            margin-bottom: 12px;
        }

        .pill-list {
            margin: 0;
            padding-left: 18px;
        }

        .pill-list li {
            margin-bottom: 4px;
        }

        .empty {
            color: #6b7280;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="section">
        <h1>{{ $trainingPath->title }}</h1>
        <p class="muted">{{ __('Programma del percorso formativo') }}</p>
    </div>

    <div class="section">
        <h2>{{ __('Dati anagrafici del percorso') }}</h2>
        <table class="grid">
            <tr>
                <td class="label">{{ __('Titolo') }}</td>
                <td>{{ $trainingPath->title }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Codice') }}</td>
                <td>{{ $trainingPath->code ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Descrizione') }}</td>
                <td>{{ $trainingPath->description ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Ordine obbligato') }}</td>
                <td>{{ $trainingPath->enforce_course_order ? __('Sì') : __('No') }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>{{ __('Dettaglio corsi') }}</h2>

        @forelse ($trainingPath->courses as $index => $course)
            <div class="course-card">
                <div class="course-meta">
                    <h3>{{ ($index + 1).'. '.$course->title }}</h3>
                </div>

                @php
                    $durationText = $course->course_duration_hours
                        ? trans_choice(':count ora|:count ore', $course->course_duration_hours, ['count' => $course->course_duration_hours])
                        : null;

                    $riskRequirementsText = $course->riskBasedRequirements->isNotEmpty()
                        ? $course->riskBasedRequirements
                            ->map(function ($requirement) {
                                $riskLevels = collect($requirement->risk_levels ?? [])
                                    ->map(fn ($riskLevel) => $riskLevel?->label() ?? null)
                                    ->filter()
                                    ->values();
                                $rawValidityTypes = $requirement->pivot->course_validity_types ?? [];
                                $validityTypes = \App\Enums\CourseRiskRequirementValidityType::labelsText(
                                    is_string($rawValidityTypes)
                                        ? (json_decode($rawValidityTypes, true) ?? [])
                                        : $rawValidityTypes
                                );

                                $details = collect([
                                    $riskLevels->isNotEmpty() ? $riskLevels->implode(', ') : null,
                                    filled($validityTypes) ? $validityTypes : null,
                                ])->filter()->implode(' - ');

                                return filled($details)
                                    ? sprintf('%s (%s)', $requirement->name, $details)
                                    : $requirement->name;
                            })
                            ->implode(', ')
                        : null;

                    $categorization = collect([
                        $courseEventTypeLabels[$course->event_type] ?? $course->event_type,
                        $course->categories->pluck('name')->implode(', '),
                    ])->filter(fn ($value) => filled($value))->implode(' | ');

                    $teachersText = $course->teacherEnrollments
                        ->map(fn ($enrollment) => trim(($enrollment->user?->surname ?? '').' '.($enrollment->user?->name ?? '')))
                        ->filter()
                        ->implode(', ');

                    $tutorsText = $course->tutorEnrollments
                        ->map(fn ($enrollment) => trim(($enrollment->user?->surname ?? '').' '.($enrollment->user?->name ?? '')))
                        ->filter()
                        ->implode(', ');

                    $courseRows = collect([
                        __('Titolo') => $course->title,
                        __('Codice') => $course->code,
                        __('Tipo') => $courseTypeLabels[$course->type] ?? $course->type,
                        __('Anno') => $course->year,
                        __('Descrizione') => $course->description,
                        __('Durata') => $durationText,
                        __('Abilitazioni di rischio') => $riskRequirementsText,
                        __('Categorizzazione') => $categorization,
                        __('Partner') => $course->partners->pluck('ragione_sociale')->implode(', '),
                        __('Docenti') => $teachersText,
                        __('Tutor') => $tutorsText,
                    ])->filter(fn ($value) => filled($value));
                @endphp

                <table class="grid">
                    @foreach ($courseRows as $label => $value)
                        <tr>
                            <td class="label">{{ $label }}</td>
                            <td>{{ $value }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @empty
            <p class="empty">{{ __('Nessun corso associato al percorso.') }}</p>
        @endforelse
    </div>
</body>
</html>
