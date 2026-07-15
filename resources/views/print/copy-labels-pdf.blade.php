<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 12.8mm 7.25mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f172a; font-family: DejaVu Sans, sans-serif; }
        .page { position: relative; width: 195.5mm; height: 271.4mm; overflow: hidden; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .label { position: absolute; width: 63.5mm; height: 33.9mm; overflow: hidden; }
        .qr { position: absolute; top: 4.45mm; left: 2mm; width: 25mm; height: 25mm; }
        .qr img { display: block; width: 25mm; height: 25mm; }
        .details { position: absolute; top: 4.7mm; right: 2mm; left: 29mm; height: 24.5mm; overflow: hidden; }
        .brand { margin: 0 0 1.4mm; color: #4f46e5; font-size: 6.2pt; font-weight: bold; letter-spacing: .02em; white-space: nowrap; }
        .number { margin: 0 0 1.4mm; font-family: DejaVu Sans Mono, monospace; font-size: 7.8pt; font-weight: bold; line-height: 1.15; white-space: nowrap; }
        .title { height: 9mm; overflow: hidden; color: #334155; font-size: 6.2pt; line-height: 1.25; }
    </style>
</head>
<body>
@foreach($copies->chunk(24) as $page)
    <div class="page">
        @foreach($page->values() as $index => $copy)
            @php
                $column = $index % 3;
                $row = intdiv($index, 3);
                $left = $column * 66;
                $top = $row * 33.9;
            @endphp
            <div class="label" style="left: {{ $left }}mm; top: {{ $top }}mm;">
                <div class="qr"><img src="{{ $codes[$copy->id] }}" alt=""></div>
                <div class="details">
                    <div class="brand">BIBLIOTHÈQUE EDSP</div>
                    <div class="number">{{ $copy->inventory_number }}</div>
                    <div class="title">{{ $copy->book->title }}</div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
