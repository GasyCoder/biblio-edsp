<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Étiquettes QR des exemplaires</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #0f172a; font-family: Arial, sans-serif; }
        .toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 14px 20px; background: #fff; border-bottom: 1px solid #cbd5e1; }
        .toolbar button { border: 0; border-radius: 6px; background: #4f46e5; color: white; padding: 10px 16px; font-weight: 700; cursor: pointer; }
        .hint { color: #475569; font-size: 12px; }
        .sheet { display: grid; grid-template-columns: repeat(3, 63.5mm); grid-auto-rows: 33.9mm; column-gap: 2.5mm; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 13mm 7.25mm; background: #fff; box-shadow: 0 8px 30px #64748b55; }
        .label { display: grid; grid-template-columns: 27mm minmax(0, 1fr); align-items: center; width: 63.5mm; height: 33.9mm; padding: 2mm; overflow: hidden; border: .2mm dashed #cbd5e1; break-inside: avoid; page-break-inside: avoid; }
        .code { display: flex; align-items: center; justify-content: center; width: 25mm; height: 25mm; }
        .code svg { display: block; width: 25mm !important; height: 25mm !important; max-width: none !important; }
        .details { min-width: 0; padding-left: 1.5mm; }
        .brand { margin-bottom: 1.2mm; color: #4f46e5; font-size: 6.5pt; font-weight: 800; letter-spacing: .04em; }
        .number { margin-bottom: 1.2mm; font: 800 8.5pt monospace; overflow-wrap: anywhere; }
        .title { display: -webkit-box; overflow: hidden; color: #334155; font-size: 6.5pt; line-height: 1.25; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; break-after: page; page-break-after: always; }
            .sheet:last-child { break-after: auto; page-break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Imprimer {{ $copies->count() }} étiquette(s)</button>
        <span class="hint">Format A4 autocollant : 24 étiquettes par feuille, 63,5 × 33,9 mm. Imprimer à 100 %, sans ajustement.</span>
    </div>
    @foreach($copies->chunk(24) as $page)
        <main class="sheet">
            @foreach($page as $copy)
                <article class="label">
                    <div class="code">{!! $codes[$copy->id] !!}</div>
                    <div class="details">
                        <div class="brand">BIBLIOTHÈQUE EDSP</div>
                        <div class="number">{{ $copy->inventory_number }}</div>
                        <div class="title">{{ $copy->book->title }}</div>
                    </div>
                </article>
            @endforeach
        </main>
    @endforeach
</body>
</html>
