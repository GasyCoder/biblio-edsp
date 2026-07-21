<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Carte de bibliothèque {{ $card->card_number }}</title>
    <style id="page-style">@page { size: 85.6mm 53.98mm; margin: 0; }</style>
    <style>
        *{box-sizing:border-box}
        body{margin:0;padding:18px;background:#e2e8f0;font-family:Arial,sans-serif;color:#0f172a}
        .toolbar{display:flex;gap:8px;margin-bottom:16px}
        .toolbar button{border:0;border-radius:6px;padding:9px 13px;font-weight:700;cursor:pointer}
        .primary{background:#4f46e5;color:#fff}
        .secondary{background:#fff;color:#334155}
        .card{box-shadow:0 8px 24px #47556944}
        @media print{body{padding:0;background:#fff}.toolbar{display:none}.card{box-shadow:none}}
        @include('print.partials.student-card-css')
    </style>
</head>
<body>
@unless($embedded)<div class="toolbar"><button class="primary" onclick="printCard('pvc')">Imprimer sur carte PVC</button><button class="secondary" onclick="printCard('a4')">Imprimer sur papier A4</button></div>@endunless
@include('print.partials.student-card', ['card' => $card, 'svg' => $codeSvg, 'branding' => $branding])
<script>function printCard(mode){document.getElementById('page-style').textContent=mode==='pvc'?'@page { size: 85.6mm 53.98mm; margin: 0; }':'@page { size: A4 portrait; margin: 10mm; }';window.print();}</script>
</body>
</html>
