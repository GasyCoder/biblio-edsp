@php
    $fullName = trim($card->student->last_name.' '.$card->student->first_name);
    $nameClass = mb_strlen($fullName) > 48 ? 'name-very-compact' : (mb_strlen($fullName) > 30 ? 'name-compact' : 'name-normal');
@endphp
<article class="card">
    <header class="top">
        @if($branding['logo_url'])
            <img class="logo" src="{{ $branding['logo_url'] }}" alt="Logo">
        @else
            <div class="logo-fallback">{{ mb_strtoupper(mb_substr($branding['name'], 0, 1)) }}</div>
        @endif
        <div class="top-text">
            <div class="school">{{ $branding['name'] }}</div>
            <div class="school-full">{{ $branding['school'] }}</div>
            <div class="subtitle">{{ $branding['institution'] }} · Carte de bibliothèque</div>
        </div>
    </header>
    <div class="accent"></div>
    @if($branding['logo_url'])
        <img class="watermark" src="{{ $branding['logo_url'] }}" alt="">
    @endif
    @if($card->student->photo_url)
        <img class="photo" src="{{ $card->student->photo_url }}" alt="Photo">
    @else
        <div class="photo-placeholder">PHOTO</div>
    @endif
    <section class="identity">
        <div class="name {{ $nameClass }}">{{ $fullName }}</div>
        <div class="meta"><strong>N° interne :</strong> {{ $card->student->registration_number }}</div>
        <div class="meta"><strong>Matricule :</strong> {{ $card->student->academic_number ?: '—' }}</div>
        <div class="meta"><strong>Niveau :</strong> {{ $card->student->academicLevel?->name ?: ($card->student->level ?: '—') }}</div>
        <div class="meta"><strong>Parcours :</strong> {{ $card->student->academicProgram?->name ?: ($card->student->program ?: '—') }}</div>
    </section>
    <div class="qr">{!! $svg !!}</div>
    <div class="card-number">{{ $card->card_number }}</div>
    <footer class="footer">
        <span>Délivrée : <strong>{{ $card->issued_at?->format('d/m/Y') }}</strong></span>
        <span>Valable jusqu’au : <strong>{{ $card->expires_at?->format('d/m/Y') ?: 'sans limite' }}</strong></span>
    </footer>
</article>
