<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <title>{{ $course->title }}</title>
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
                margin: 0 0 24px;
                padding-bottom: 12px;
                border-bottom: 1px solid #d1d5db;
            }

            .question {
                margin-bottom: 20px;
                page-break-inside: avoid;
            }

            .question-title {
                font-size: 14px;
                font-weight: 700;
                margin: 0 0 8px;
            }

            .answers {
                margin: 0;
                padding-left: 22px;
            }

            .answers li {
                margin-bottom: 4px;
            }
        </style>
    </head>
    <body>
        <h1>{{ $course->title }}</h1>

        @foreach ($module->quizQuestions as $question)
            <div class="question">
                <p class="question-title">{{ $loop->iteration }}. {{ $question->text }}</p>

                <ol class="answers" type="A">
                    @foreach ($question->answers->take(4) as $answer)
                        <li>{{ $answer->text }}</li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </body>
</html>
