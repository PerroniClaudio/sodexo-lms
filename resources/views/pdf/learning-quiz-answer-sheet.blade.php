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
                margin: 32px;
            }

            .header {
                width: 100%;
                margin-bottom: 28px;
            }

            .header td {
                vertical-align: top;
            }

            .title {
                font-size: 22px;
                font-weight: 700;
                padding-right: 24px;
            }

            .qr-code {
                width: 60px;
                height: 60px;
                text-align: right;
            }

            .qr-code img {
                display: block;
                width: 60px;
                height: 60px;
                margin-left: auto;
            }

            .questions-table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }

            .sheet {
                page-break-after: always;
            }

            .sheet:last-child {
                page-break-after: auto;
            }

            .questions-table th,
            .questions-table td {
                border: 1px solid #9ca3af;
                padding: 10px 8px;
                text-align: center;
            }

            .questions-table th:first-child,
            .questions-table td:first-child {
                width: 18%;
            }

            .answer-square {
                display: inline-block;
                width: 18px;
                height: 18px;
                border: 1px solid #111827;
            }
        </style>
    </head>
    <body>
        @foreach ($userSheets as $userSheet)
            <div class="sheet">
                <table class="header">
                    <tr>
                        <td class="title">{{ $course->title }}</td>
                        <td class="qr-code">
                            <img src="{{ $userSheet['qrCodeDataUri'] }}" alt="QR Code">
                        </td>
                    </tr>
                </table>

                <table class="questions-table">
                    <thead>
                        <tr>
                            <th>{{ __('Domanda') }}</th>
                            @foreach ($answerOptionLabels as $answerOptionLabel)
                                <th>{{ $answerOptionLabel }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($userSheet['questionNumbers'] as $questionNumber)
                            <tr>
                                <td>{{ $questionNumber }}</td>

                                @foreach ($answerOptionLabels as $answerOptionLabel)
                                    <td>
                                        <span class="answer-square" aria-label="{{ __('Risposta :option', ['option' => $answerOptionLabel]) }}"></span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </body>
</html>
