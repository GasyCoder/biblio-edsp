<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Cartes de bibliothèque</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#e2e8f0;font-family:Arial,sans-serif;color:#0f172a}
        .toolbar{display:flex;align-items:center;gap:12px;padding:14px 20px;background:#fff}
        .toolbar button{border:0;border-radius:6px;background:#4f46e5;color:#fff;padding:10px 16px;font-weight:700;cursor:pointer}
        .sheet{display:grid;grid-template-columns:repeat(2,85.6mm);grid-auto-rows:53.98mm;gap:3mm;width:190mm;min-height:277mm;margin:16px auto;padding:0;background:#fff}
        .sheet .card{border-style:dotted}
        @page{size:A4 portrait;margin:10mm}
        @media print{body{background:#fff}.toolbar{display:none}.sheet{margin:0;break-after:page}.sheet:last-child{break-after:auto}}
        @include('print.partials.student-card-css')
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Imprimer {{ $cards->count() }} cartes</button><span>Planche A4 · format CR80</span></div>
@foreach($cards->chunk(10) as $page)
    <main class="sheet">
        @foreach($page as $card)
            @include('print.partials.student-card', ['card' => $card, 'svg' => $codes[$card->id], 'branding' => $branding])
        @endforeach
    </main>
@endforeach
</body>
</html>
