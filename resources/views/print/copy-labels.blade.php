<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>QR codes des exemplaires</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 24px; color: #0f172a; }
        .toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; }
        .toolbar button { border: 0; border-radius: 6px; background: #4f46e5; color: white; padding: 10px 16px; font-weight: 700; cursor: pointer; }
        .labels { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .label { border: 1px dashed #94a3b8; padding: 14px; text-align: center; break-inside: avoid; }
        .title { min-height: 34px; font-size: 12px; font-weight: 700; margin-bottom: 8px; }
        .code svg { width: 140px; max-width: 100%; height: 140px; }
        .number { font: 700 13px monospace; margin-top: 8px; }
        @media print { body { padding: 0; } .toolbar { display: none; } .labels { gap: 0; } .label { border-color: #cbd5e1; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">Imprimer les QR codes</button><span>{{ $copies->count() }} exemplaire(s)</span></div>
    <div class="labels">
        @foreach($copies as $copy)
            <div class="label">
                <div class="title">Bibliothèque EDSP<br>{{ $copy->book->title }}</div>
                <div class="code">{!! $codes[$copy->id] !!}</div>
                <div class="number">{{ $copy->inventory_number }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
