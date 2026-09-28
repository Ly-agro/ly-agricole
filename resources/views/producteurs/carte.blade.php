{{-- Vue PDF (dompdf) : CSS 2.1 en ligne, pas de Tailwind ni de flexbox. Dimensions réelles d'une carte ID-1 : 85,6 × 54 mm. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Carte {{ $producteur->code }}</title>
    <style>
        @page { margin: 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; font-size: 9pt; }
        .consigne { color: #78716c; font-size: 8pt; margin-bottom: 4mm; }
        .carte {
            width: 85.6mm; height: 54mm; border: 0.3mm dashed #a8a29e; border-radius: 3mm;
            position: relative; overflow: hidden;
        }
        /* Padding plutôt que line-height : dompdf place mal le texte avec un line-height haut (texte coupé). */
        .bandeau { background: #065f46; color: #fff; height: 6.4mm; padding: 2.6mm 3mm 0 3mm; }
        .bandeau .marque { font-weight: bold; font-size: 10pt; letter-spacing: 0.5pt; }
        .bandeau .devise { float: right; font-size: 6pt; padding-top: 1mm; }
        .photo { position: absolute; left: 3mm; top: 12mm; width: 22mm; height: 27mm; border-radius: 1.5mm; }
        .sans-photo { background: #e7e5e4; color: #78716c; font-size: 6pt; text-align: center; line-height: 27mm; }
        .identite { position: absolute; left: 28mm; top: 12mm; width: 30mm; }
        .nom { font-weight: bold; font-size: 9.5pt; line-height: 11pt; }
        .prenoms { font-size: 8.5pt; line-height: 10pt; }
        .lieu { color: #57534e; font-size: 7pt; margin-top: 2mm; }
        .qr { position: absolute; right: 3mm; top: 12mm; width: 24mm; height: 24mm; }
        .code { position: absolute; left: 3mm; right: 3mm; bottom: 3mm; font-family: 'DejaVu Sans Mono', monospace;
                font-size: 11pt; font-weight: bold; letter-spacing: 1pt; }
        .code small { font-family: 'DejaVu Sans', sans-serif; font-weight: normal; font-size: 6pt; letter-spacing: 0; color: #78716c; }
    </style>
</head>
<body>
    <p class="consigne">Carte producteur — découper en suivant les pointillés. Imprimée le {{ now()->format('d/m/Y') }}.</p>

    <div class="carte">
        <div class="bandeau">
            <span class="marque">LY AGRICOLE</span>
            <span class="devise">Cultiver – Élever – Durer</span>
        </div>

        @if ($photo)
            <img class="photo" src="{{ $photo }}" alt="">
        @else
            <div class="photo sans-photo">sans photo</div>
        @endif

        <div class="identite">
            <div class="nom">{{ $producteur->nom }}</div>
            <div class="prenoms">{{ $producteur->prenoms }}</div>
            <div class="lieu">{{ $producteur->village->nom }}<br>{{ $producteur->village->zone->nom }}</div>
        </div>

        <img class="qr" src="{{ $qr }}" alt="">

        <div class="code">{{ $producteur->code }} <small>carte producteur</small></div>
    </div>
</body>
</html>
