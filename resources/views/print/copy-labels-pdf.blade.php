<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 portrait; margin: 12.8mm 7.25mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f172a; font-family: DejaVu Sans, sans-serif; }
        .page { width: 195.5mm; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        table { width: 195.5mm; border-collapse: collapse; table-layout: fixed; }
        tr { height: 33.9mm; }
        td.label { width: 63.5mm; height: 33.9mm; padding: 2mm; overflow: hidden; vertical-align: middle; }
        td.gap { width: 2.5mm; padding: 0; }
        .qr { float: left; width: 25mm; height: 25mm; }
        .qr img { display: block; width: 25mm; height: 25mm; }
        .details { margin-left: 27mm; padding-top: 2mm; }
        .brand { margin-bottom: 1.2mm; color: #4f46e5; font-size: 6.5pt; font-weight: bold; letter-spacing: .03em; }
        .number { margin-bottom: 1.2mm; font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; font-weight: bold; }
        .title { max-height: 12mm; overflow: hidden; color: #334155; font-size: 6.5pt; line-height: 1.25; }
    </style>
</head>
<body>
@foreach($copies->chunk(24) as $page)
    <div class="page">
        <table>
            @foreach($page->chunk(3) as $row)
                <tr>
                    @for($column = 0; $column < 3; $column++)
                        @if($column > 0)<td class="gap"></td>@endif
                        <td class="label">
                            @if($copy = $row->values()->get($column))
                                <div class="qr"><img src="{{ $codes[$copy->id] }}" alt=""></div>
                                <div class="details">
                                    <div class="brand">BIBLIOTHÈQUE EDSP</div>
                                    <div class="number">{{ $copy->inventory_number }}</div>
                                    <div class="title">{{ $copy->book->title }}</div>
                                </div>
                            @endif
                        </td>
                    @endfor
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
</body>
</html>
