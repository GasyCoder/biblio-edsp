<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;padding:14px;font-family:DejaVu Sans,Arial,sans-serif;color:#0f172a;font-size:10px;background:#fff}
        h1{margin:0;font-size:16px}
        .head{border-bottom:2px solid #1e3a8a;padding-bottom:8px;margin-bottom:10px}
        .muted{color:#475569;font-size:9px;margin-top:3px}
        .toolbar{margin-bottom:12px;display:flex;gap:8px}
        .toolbar button{border:0;border-radius:6px;background:#4f46e5;color:#fff;padding:9px 14px;font-weight:700;cursor:pointer}
        table{width:100%;border-collapse:collapse}
        th,td{border:.5px solid #cbd5e1;padding:4px 6px;text-align:left;vertical-align:top}
        th{background:#eef2ff;font-size:9px;text-transform:uppercase;letter-spacing:.03em}
        tbody tr:nth-child(even){background:#f8fafc}
        td.num,th.num{text-align:center}
        .count{margin-bottom:6px;font-size:9px;color:#475569}
        .foot{margin-top:12px;font-size:8px;color:#64748b;text-align:center}
        @page{size:A4 landscape;margin:8mm}
        @media print{body{padding:0}.toolbar{display:none}thead{display:table-header-group}tr{break-inside:avoid}}
    </style>
</head>
<body>
@unless($embedded ?? false)
    <div class="toolbar"><button onclick="window.print()">Imprimer</button></div>
@endunless

<div class="head">
    <h1>{{ $title }}</h1>
    <div class="muted">{{ $subtitle }}</div>
    @isset($context)<div class="muted">{{ $context }}</div>@endisset
</div>

<div class="count">{{ count($rows) }} ligne(s)</div>

<table>
    <thead>
    <tr>
        @foreach($columns as $column)
            <th @class(['num' => in_array($loop->index, $numeric ?? [], true)])>{{ $column }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            @foreach(array_values($row) as $index => $cell)
                <td @class(['num' => in_array($index, $numeric ?? [], true)])>{{ $cell }}</td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($columns) }}">Aucune donnée sur la période.</td></tr>
    @endforelse
    </tbody>
</table>

<div class="foot">Généré le {{ now()->format('d/m/Y à H:i') }} · Bibliothèque EDSP</div>
</body>
</html>
