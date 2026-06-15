<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <title>{{ $exercise->title }}</title>
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                color: #111827;
                line-height: 1.5;
                margin: 32px;
            }

            h1 {
                font-size: 20px;
                margin: 0 0 18px;
                padding-bottom: 12px;
                border-bottom: 1px solid #d1d5db;
            }

            h2 {
                font-size: 15px;
                margin: 24px 0 10px;
            }

            .meta {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            .meta td {
                border: 1px solid #d1d5db;
                padding: 8px;
            }

            .question {
                margin-bottom: 18px;
                page-break-inside: avoid;
            }

            .question-title {
                font-weight: 700;
                margin-bottom: 6px;
            }

            .answer {
                border: 1px solid #d1d5db;
                padding: 10px;
                min-height: 42px;
            }
        </style>
    </head>
    <body>
        <h1>{{ $exercise->title }}</h1>

        <table class="meta">
            <tr>
                <td><strong>{{ __('Corso') }}</strong><br>{{ $course->title }}</td>
                <td><strong>{{ __('Modulo') }}</strong><br>{{ $module->title }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('Utente') }}</strong><br>{{ $submission->user?->full_name ?? $submission->user?->email }}</td>
                <td><strong>{{ __('Completata il') }}</strong><br>{{ $submission->completed_at?->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td><strong>{{ __('Tempo registrato') }}</strong><br>{{ gmdate('H:i:s', $submission->elapsed_seconds) }}</td>
                <td><strong>{{ __('Autovalutazione') }}</strong><br>{{ $exercise->self_evaluation_original_name ?: __('Non presente') }}</td>
            </tr>
        </table>

        <h2>{{ __('Domande e risposte') }}</h2>

        @php
            $answersByQuestion = $submission->answers->keyBy('video_exercise_question_id');
        @endphp

        @foreach ($exercise->questions as $question)
            <div class="question">
                <div class="question-title">{{ $loop->iteration }}. {{ $question->text }}</div>
                <div class="answer">{{ $answersByQuestion->get($question->getKey())?->answer_text }}</div>
            </div>
        @endforeach
    </body>
</html>
