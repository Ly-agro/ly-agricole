{{-- Vue PDF (dompdf) : CSS 2.1 en ligne. --}}
@php($numerique = ['fcfa', 'kg', 'pour_mille', 'nombre'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $tableau->titre }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        .entete { border-bottom: 0.5mm solid #065f46; margin-bottom: 4mm; }
        .entete td { padding: 0 0 2mm 0; vertical-align: bottom; }
        .marque { color: #065f46; font-weight: bold; font-size: 12pt; }
        .titre { text-align: right; }
        .titre strong { font-size: 11pt; }
        .note { color: #57534e; margin: 0 0 3mm 0; }
        .donnees th { text-align: left; color: #57534e; font-weight: normal; border-bottom: 0.3mm solid #a8a29e; padding: 1.5mm 1mm; }
        .donnees td { border-bottom: 0.2mm solid #e7e5e4; padding: 1.2mm 1mm; }
        /* Plus spécifique que « .donnees th » : sinon les en-têtes chiffrés restent à gauche. */
        .donnees .nombre { text-align: right; white-space: nowrap; }
        .total td { font-weight: bold; border-top: 0.4mm solid #1c1917; border-bottom: none; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td><span class="marque">LY AGRICOLE</span></td>
            <td class="titre"><strong>{{ $tableau->titre }}</strong><br>au {{ now()->format('d/m/Y à H:i') }}</td>
        </tr>
    </table>

    @if ($tableau->note)<p class="note">{{ $tableau->note }}</p>@endif

    <table class="donnees">
        <thead>
            <tr>
                @foreach ($tableau->colonnes as [$libelle, $type])
                    <th @class(['nombre' => in_array($type, $numerique, true)])>{{ $libelle }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($tableau->lignes as $ligne)
                <tr>
                    @foreach ($tableau->colonnes as $i => [$libelle, $type])
                        <td @class(['nombre' => in_array($type, $numerique, true)])>{{ \App\Support\Tableau::afficher($ligne[$i], $type) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($tableau->colonnes) }}">Rien à signaler.</td></tr>
            @endforelse
            @if ($tableau->totaux && $tableau->lignes !== [])
                <tr class="total">
                    @foreach ($tableau->colonnes as $i => [$libelle, $type])
                        <td @class(['nombre' => in_array($type, $numerique, true)])>{{ $tableau->totaux[$i] === null ? '' : \App\Support\Tableau::afficher($tableau->totaux[$i], $type) }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
