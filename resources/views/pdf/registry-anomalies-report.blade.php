<!DOCTYPE html>
<html lang="it">
    <head>
        <meta charset="utf-8">
        <title>Anomalie Anagrafica</title>
        <style>
            @page { margin: 18mm 14mm; }
            * { box-sizing: border-box; }
            body { color: #14213d; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.35; }
            h1, h2, h3, p { margin: 0; }
            h1 { font-size: 30px; letter-spacing: -1px; }
            h2 { color: #0b3c5d; font-size: 17px; margin-bottom: 10px; }
            h3 { font-size: 10px; text-transform: uppercase; letter-spacing: .7px; }
            .cover { background: #f4f8fb; border-left: 11px solid #e76f51; min-height: 115mm; padding: 16mm 18mm; }
            .eyebrow { color: #e76f51; font-size: 10px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
            .subtitle { color: #5f6b7a; font-size: 13px; margin-top: 10px; width: 440px; }
            .generated { color: #667085; margin-top: 18mm; }
            .score { background: #0b3c5d; border-radius: 5px; color: #fff; margin-top: 18mm; padding: 13px 16px; width: 280px; }
            .score-number { font-size: 34px; font-weight: bold; }
            .score-label { font-size: 10px; margin-top: 2px; }
            .page-break { page-break-before: always; }
            .section-header { border-bottom: 2px solid #0b3c5d; margin-bottom: 14px; padding-bottom: 6px; }
            .kpis { border-collapse: separate; border-spacing: 7px; margin: 0 -7px 15px; width: calc(100% + 14px); }
            .kpi { background: #f4f8fb; border-top: 4px solid #2a9d8f; padding: 11px; vertical-align: top; width: 25%; }
            .kpi.alert { border-top-color: #e76f51; }
            .kpi.warning { border-top-color: #e9c46a; }
            .kpi-number { color: #0b3c5d; font-size: 22px; font-weight: bold; margin-top: 5px; }
            .kpi-label { color: #667085; font-size: 8px; text-transform: uppercase; }
            .panel { border: 1px solid #d8e2ea; margin-bottom: 14px; padding: 11px; }
            .split { border-collapse: separate; border-spacing: 10px; margin: 0 -10px; width: calc(100% + 20px); }
            .split td { vertical-align: top; width: 50%; }
            .bar { background: #e6edf2; height: 10px; margin: 8px 0 5px; overflow: hidden; }
            .bar > span { background: #2a9d8f; display: block; height: 100%; }
            .bar.warning > span { background: #e9c46a; }
            .bar.alert > span { background: #e76f51; }
            .small { color: #667085; font-size: 8px; }
            .matrix { border-collapse: separate; border-spacing: 7px; margin: 0 -7px; width: calc(100% + 14px); }
            .matrix td { color: #fff; padding: 12px; text-align: center; width: 33.333%; }
            .critical { background: #b42318; }
            .high { background: #e76f51; }
            .medium { background: #e9c46a; color: #5c4300 !important; }
            .matrix strong { display: block; font-size: 24px; }
            table.data { border-collapse: collapse; font-size: 8px; width: 100%; }
            table.data th { background: #0b3c5d; color: #fff; font-size: 7px; letter-spacing: .5px; padding: 7px; text-align: left; text-transform: uppercase; }
            table.data td { border-bottom: 1px solid #d8e2ea; padding: 7px; vertical-align: top; }
            table.data tr:nth-child(even) td { background: #f8fafc; }
            .badge { border-radius: 10px; display: inline-block; font-size: 7px; font-weight: bold; padding: 3px 6px; white-space: nowrap; }
            .badge-critical { background: #fee4e2; color: #b42318; }
            .badge-high { background: #fef0c7; color: #b54708; }
            .badge-medium { background: #fef9c3; color: #854d0e; }
            .badge-ok { background: #d1fadf; color: #027a48; }
            .footer { color: #98a2b3; font-size: 7px; margin-top: 10px; text-align: right; }
        </style>
    </head>
    <body>
        <section class="cover">
            <p class="eyebrow">Audit qualità dati</p>
            <h1>Anomalie<br>Anagrafica</h1>
            <p class="subtitle">Quadro operativo di completezza, coerenza e classificazione del rischio per utenti e mansioni.</p>
            <div class="score">
                <div class="score-number">{{ $overall_completeness_percentage }}%</div>
                <div class="score-label">Completezza complessiva dei dati obbligatori</div>
            </div>
            <p class="generated">Generato il {{ $generated_at }}<br>Perimetro: {{ $scope_label }}.</p>
        </section>

        <section class="page-break">
            <div class="section-header"><h2>Sintesi esecutiva</h2></div>
            <table class="kpis"><tr>
                <td class="kpi"><p class="kpi-label">Utenti analizzati</p><p class="kpi-number">{{ $total_users }}</p></td>
                <td class="kpi"><p class="kpi-label">Completezza dati</p><p class="kpi-number">{{ $overall_completeness_percentage }}%</p></td>
                <td class="kpi warning"><p class="kpi-label">Profili completi</p><p class="kpi-number">{{ $fully_complete_percentage }}%</p></td>
                <td class="kpi alert"><p class="kpi-label">Anomalie rilevate</p><p class="kpi-number">{{ $anomalies->count() }}</p></td>
            </tr></table>

            <table class="split"><tr>
                @foreach (['workers' => 'Lavoratori', 'other_users' => 'Altri utenti'] as $key => $label)
                    @php($section = $sections[$key])
                    <td><div class="panel">
                        <h3>{{ $label }}</h3>
                        <p class="small">{{ $section['complete'] }} profili completi su {{ $section['total'] }}</p>
                        <div class="bar {{ $section['completeness_percentage'] < 75 ? 'alert' : ($section['completeness_percentage'] < 90 ? 'warning' : '') }}"><span style="width: {{ $section['completeness_percentage'] }}%"></span></div>
                        <strong>{{ $section['completeness_percentage'] }}%</strong> <span class="small">campi obbligatori compilati - {{ $section['complete_percentage'] }}% profili completi</span>
                    </div></td>
                @endforeach
            </tr></table>

            <div class="panel">
                <h3>Matrice priorità intervento</h3>
                <table class="matrix"><tr>
                    <td class="critical"><strong>{{ $severity_counts['Critica'] }}</strong>Critiche</td>
                    <td class="high"><strong>{{ $severity_counts['Alta'] }}</strong>Alte</td>
                    <td class="medium"><strong>{{ $severity_counts['Media'] }}</strong>Medie</td>
                </tr></table>
            </div>
        </section>

        <section class="page-break">
            <div class="section-header"><h2>Mansioni e classificazione rischio</h2><p class="small">Conteggio utenti con assegnazione attiva alla data di generazione.</p></div>
            <table class="data">
                <thead><tr><th>Mansione</th><th>Utenti associati</th><th>Rischio configurato</th><th>Stato</th></tr></thead>
                <tbody>
                    @forelse ($job_tasks as $jobTask)
                        <tr>
                            <td>{{ $jobTask['name'] }}</td>
                            <td>{{ $jobTask['users_count'] }}</td>
                            <td>{{ $jobTask['risk'] }}</td>
                            <td><span class="badge {{ $jobTask['classified'] ? 'badge-ok' : 'badge-critical' }}">{{ $jobTask['classified'] ? 'Classificata' : 'Da classificare' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Nessuna mansione configurata.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <section class="page-break">
            <div class="section-header"><h2>Dettaglio anomalie e azioni correttive</h2><p class="small">Elenco completo ordinato per gravità.</p></div>
            <table class="data">
                <thead><tr><th>Sezione</th><th>Utente</th><th>Anomalia</th><th>Dettaglio</th><th>Intervento richiesto</th></tr></thead>
                <tbody>
                    @forelse ($anomalies as $anomaly)
                        <tr>
                            <td>{{ $anomaly['section'] }}</td>
                            <td>{{ $anomaly['user_name'] }}</td>
                            <td><span class="badge badge-{{ ['Critica' => 'critical', 'Alta' => 'high', 'Media' => 'medium'][$anomaly['severity']] }}">{{ $anomaly['severity'] }}</span><br>{{ $anomaly['category'] }}</td>
                            <td>{{ $anomaly['detail'] }}</td>
                            <td>{{ $anomaly['action'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Nessuna anomalia rilevata.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="footer">Report Anomalie Anagrafica - {{ $generated_at }}</p>
        </section>
    </body>
</html>
