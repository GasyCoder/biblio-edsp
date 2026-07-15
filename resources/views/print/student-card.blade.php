<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Carte de bibliothèque {{ $card->card_number }}</title>
    <style id="page-style">@page { size: 85.6mm 53.98mm; margin: 0; }</style>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:18px;background:#e2e8f0;font-family:Arial,sans-serif;color:#0f172a}.toolbar{display:flex;gap:8px;margin-bottom:16px}.toolbar button{border:0;border-radius:6px;padding:9px 13px;font-weight:700;cursor:pointer}.primary{background:#4f46e5;color:#fff}.secondary{background:#fff;color:#334155}.card{position:relative;width:85.6mm;height:53.98mm;overflow:hidden;border:.25mm solid #94a3b8;border-radius:3mm;background:#fff;box-shadow:0 8px 24px #47556944}.top{position:absolute;inset:0 0 auto;height:11mm;padding:2.4mm 4mm;background:#111a33;color:#fff}.school{font-size:10pt;font-weight:800;letter-spacing:.04em}.subtitle{margin-top:.5mm;font-size:5.8pt;letter-spacing:.12em;text-transform:uppercase;color:#c7d2fe}.photo{position:absolute;top:14mm;left:3mm;width:17mm;height:24mm;border:.25mm solid #cbd5e1;border-radius:1.5mm;object-fit:cover}.photo-placeholder{position:absolute;top:14mm;left:3mm;width:17mm;height:24mm;border:.25mm dashed #94a3b8;border-radius:1.5mm;background:#f1f5f9;text-align:center;padding-top:9mm;font-size:6pt;color:#64748b}.identity{position:absolute;top:13.5mm;left:22.5mm;width:39mm;height:30mm;overflow:hidden}.name{max-height:10.5mm;overflow:hidden;font-weight:800;line-height:1.08;text-transform:uppercase;overflow-wrap:anywhere;word-break:break-word}.name-normal{font-size:9pt}.name-compact{font-size:7.4pt}.name-very-compact{font-size:6.2pt}.meta{margin-top:.9mm;font-size:5.9pt;line-height:1.18;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.meta strong{color:#0f172a}.qr{position:absolute;top:14mm;right:3mm;width:19.5mm;height:19.5mm}.qr svg{display:block;width:19.5mm!important;height:19.5mm!important}.card-number{position:absolute;top:34.5mm;right:2.5mm;width:20.5mm;text-align:center;font:700 5.5pt monospace}.footer{position:absolute;right:3mm;bottom:2.5mm;left:3mm;display:flex;justify-content:space-between;border-top:.2mm solid #e2e8f0;padding-top:1.4mm;font-size:5.5pt;color:#64748b}.footer strong{color:#334155}@media print{body{padding:0;background:#fff}.toolbar{display:none}.card{box-shadow:none}}
    </style>
</head>
<body>
@unless($embedded)<div class="toolbar"><button class="primary" onclick="printCard('pvc')">Imprimer sur carte PVC</button><button class="secondary" onclick="printCard('a4')">Imprimer sur papier A4</button></div>@endunless
<article class="card">
    <header class="top"><div class="school">BIBLIOTHÈQUE EDSP</div><div class="subtitle">Université de Mahajanga · Carte de bibliothèque</div></header>
    @if($card->student->photo_url)<img class="photo" src="{{ $card->student->photo_url }}" alt="Photo">@else<div class="photo-placeholder">PHOTO</div>@endif
    @php
        $fullName = trim($card->student->last_name.' '.$card->student->first_name);
        $nameClass = mb_strlen($fullName) > 48 ? 'name-very-compact' : (mb_strlen($fullName) > 30 ? 'name-compact' : 'name-normal');
    @endphp
    <section class="identity"><div class="name {{ $nameClass }}">{{ $fullName }}</div><div class="meta"><strong>N° interne :</strong> {{ $card->student->registration_number }}</div><div class="meta"><strong>Matricule :</strong> {{ $card->student->academic_number ?: '—' }}</div><div class="meta"><strong>Niveau :</strong> {{ $card->student->academicLevel?->name ?: ($card->student->level ?: '—') }}</div><div class="meta"><strong>Parcours :</strong> {{ $card->student->academicProgram?->name ?: ($card->student->program ?: '—') }}</div></section>
    <div class="qr">{!! $codeSvg !!}</div><div class="card-number">{{ $card->card_number }}</div>
    <footer class="footer"><span>Délivrée : <strong>{{ $card->issued_at?->format('d/m/Y') }}</strong></span><span>Valable jusqu’au : <strong>{{ $card->expires_at?->format('d/m/Y') ?: 'sans limite' }}</strong></span></footer>
</article>
<script>function printCard(mode){document.getElementById('page-style').textContent=mode==='pvc'?'@page { size: 85.6mm 53.98mm; margin: 0; }':'@page { size: A4 portrait; margin: 10mm; }';window.print();}</script>
</body>
</html>
