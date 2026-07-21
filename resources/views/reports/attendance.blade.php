<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport d’assiduité</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;padding:0;font-family:DejaVu Sans,Arial,sans-serif;color:#0f172a;font-size:10px}
        h1{margin:0;font-size:17px}
        h2{margin:18px 0 6px;font-size:12px;border-bottom:1px solid #cbd5e1;padding-bottom:3px}
        .head{border-bottom:2px solid #1e3a8a;padding-bottom:8px;margin-bottom:12px}
        .muted{color:#475569;font-size:9px;margin-top:3px}
        table{width:100%;border-collapse:collapse;margin-top:4px}
        th,td{border:.5px solid #cbd5e1;padding:4px 5px;text-align:left}
        th{background:#eef2ff;font-size:9px;text-transform:uppercase;letter-spacing:.03em}
        td.num,th.num{text-align:center}
        .kpis{width:100%;border-collapse:collapse;margin-top:6px}
        .kpis td{border:.5px solid #cbd5e1;padding:6px;width:25%}
        .kpis strong{display:block;font-size:15px;margin-bottom:2px}
        @media screen{body{padding:14px}}
        @media print{.noprint{display:none}}
        .foot{margin-top:14px;font-size:8px;color:#64748b;text-align:center}
    </style>
</head>
<body>
@if($printable ?? false)
    <div class="noprint" style="margin-bottom:12px">
        <button onclick="window.print()" style="border:0;border-radius:6px;background:#4f46e5;color:#fff;padding:9px 14px;font-weight:700;cursor:pointer">Imprimer</button>
    </div>
@endif
<div class="head">
    <h1>Rapport d’assiduité des étudiants</h1>
    <div class="muted">
        Période du {{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}
        au {{ \Carbon\Carbon::parse($filters['to'])->format('d/m/Y') }}
        · {{ $granularityLabel }} · Regroupement : {{ $groupLabel }}
    </div>
    <div class="muted">Assiduité = fréquentation réelle : un étudiant est « présent » un jour dès qu’un passage est enregistré ce jour-là.</div>
</div>

<table class="kpis">
    <tr>
        <td><strong>{{ $kpis['cohort'] }}</strong>Effectif suivi</td>
        <td><strong>{{ $kpis['present'] }}</strong>Présents</td>
        <td><strong>{{ $kpis['absent'] }}</strong>Absents</td>
        <td><strong>{{ $kpis['attendanceRate'] }}%</strong>Taux de fréquentation</td>
    </tr>
    <tr>
        <td><strong>{{ $kpis['totalVisits'] }}</strong>Passages enregistrés</td>
        <td><strong>{{ $kpis['totalPresenceDays'] }}</strong>Jours de présence cumulés</td>
        <td><strong>{{ $kpis['avgDaysPerPresent'] }}</strong>Jours moyens / présent</td>
        <td><strong>{{ $kpis['openDays'] }}</strong>Jours d’ouverture</td>
    </tr>
</table>

<h2>Assiduité par {{ mb_strtolower($groupLabel) }}</h2>
<table>
    <thead>
    <tr>
        <th>{{ $groupLabel }}</th>
        <th class="num">Effectif</th>
        <th class="num">Présents</th>
        <th class="num">Absents</th>
        <th class="num">Taux</th>
        <th class="num">Jours de présence</th>
        <th class="num">Jours moyens</th>
    </tr>
    </thead>
    <tbody>
    @forelse($breakdown as $row)
        <tr>
            <td>{{ $row['label'] }}</td>
            <td class="num">{{ $row['cohort'] }}</td>
            <td class="num">{{ $row['present'] }}</td>
            <td class="num">{{ $row['absent'] }}</td>
            <td class="num">{{ $row['rate'] }}%</td>
            <td class="num">{{ $row['presenceDays'] }}</td>
            <td class="num">{{ $row['avgDays'] }}</td>
        </tr>
    @empty
        <tr><td colspan="7">Aucun étudiant dans ce périmètre.</td></tr>
    @endforelse
    </tbody>
</table>

<h2>Étudiants les plus assidus</h2>
<div class="muted">Score = jours de présence ×{{ $weights['presence'] }} + consultations ×{{ $weights['consultation'] }} + prêts ×{{ $weights['loan'] }}.</div>
<table>
    <thead>
    <tr>
        <th class="num">Rang</th>
        <th>N° bibliothèque</th>
        <th>Nom et prénoms</th>
        <th>{{ $groupLabel }}</th>
        <th class="num">Jours présents</th>
        <th class="num">Consult.</th>
        <th class="num">Prêts</th>
        <th class="num">Score</th>
    </tr>
    </thead>
    <tbody>
    @forelse($ranking as $index => $row)
        <tr>
            <td class="num">{{ $index + 1 }}</td>
            <td>{{ $row['registration_number'] }}</td>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['group'] }}</td>
            <td class="num">{{ $row['daysPresent'] }}</td>
            <td class="num">{{ $row['consultations'] }}</td>
            <td class="num">{{ $row['loans'] }}</td>
            <td class="num">{{ $row['score'] }}</td>
        </tr>
    @empty
        <tr><td colspan="8">Aucune activité sur la période.</td></tr>
    @endforelse
    </tbody>
</table>

@if($absentees->count())
    <h2>Étudiants absents sur la période ({{ $absentees->count() }})</h2>
    <table>
        <thead>
        <tr>
            <th>N° bibliothèque</th>
            <th>Nom et prénoms</th>
            <th>{{ $groupLabel }}</th>
        </tr>
        </thead>
        <tbody>
        @foreach($absentees as $row)
            <tr>
                <td>{{ $row['registration_number'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['group'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="foot">Généré le {{ now()->format('d/m/Y à H:i') }} · Bibliothèque EDSP</div>
</body>
</html>
