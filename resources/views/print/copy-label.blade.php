<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $copy->inventory_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 16px; background: #fff; color: #0f172a; font-family: Arial, sans-serif; }
        button { margin-bottom: 14px; border: 0; border-radius: 6px; background: #4f46e5; color: #fff; padding: 9px 14px; font-weight: 700; }
        .label { display: grid; grid-template-columns: 27mm minmax(0, 1fr); align-items: center; width: 63.5mm; height: 33.9mm; padding: 2mm; overflow: hidden; border: .2mm dashed #94a3b8; }
        .code { display: flex; align-items: center; justify-content: center; width: 25mm; height: 25mm; }
        .code svg { display: block; width: 25mm !important; height: 25mm !important; max-width: none !important; }
        .details { min-width: 0; padding-left: 1.5mm; }
        .brand { margin-bottom: 1.2mm; color: #4f46e5; font-size: 6.5pt; font-weight: 800; letter-spacing: .04em; }
        .number { margin-bottom: 1.2mm; font: 800 8.5pt monospace; overflow-wrap: anywhere; }
        .title { display: -webkit-box; overflow: hidden; color: #334155; font-size: 6.5pt; line-height: 1.25; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
        @page { size: A4 portrait; margin: 13mm 7.25mm; }
        @media print { body { padding: 0; } button { display: none; } }
    </style>
</head>
<body>
    @unless($embedded)<button onclick="window.print()">Imprimer l’étiquette</button>@endunless
    <article class="label">
        <div class="code">{!! $codeSvg !!}</div>
        <div class="details">
            <div class="brand">BIBLIOTHÈQUE EDSP</div>
            <div class="number">{{ $copy->inventory_number }}</div>
            <div class="title">{{ $copy->book->title }}</div>
        </div>
    </article>
</body>
</html>
