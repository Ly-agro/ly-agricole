@props(['serie'])

@php
    use App\Support\Graphique;

    $largeur = 640;
    $hauteur = 240;
    $gauche = 46;
    $bas = 28;
    $haut = 12;
    $zone = $hauteur - $bas - $haut;
    $plafond = Graphique::plafond((int) collect($serie)->flatMap(fn ($m) => [$m['entrees'], $m['sorties']])->max());
    $pas = ($largeur - $gauche) / max(count($serie), 1);
    $barre = min(22, $pas / 3);
    $y = fn (int $v) => $haut + $zone - intdiv($v * $zone, $plafond);
@endphp

<svg viewBox="0 0 {{ $largeur }} {{ $hauteur }}" class="h-auto w-full" role="img"
    aria-label="Entrées et sorties d'argent par mois">
    @foreach ([0, 1, 2, 3, 4] as $i)
        @php $valeur = intdiv($plafond * $i, 4); @endphp
        <line x1="{{ $gauche }}" x2="{{ $largeur }}" y1="{{ $y($valeur) }}" y2="{{ $y($valeur) }}" class="stroke-stone-200" stroke-width="1" />
        <text x="{{ $gauche - 8 }}" y="{{ $y($valeur) + 4 }}" text-anchor="end" class="fill-stone-500" font-size="11">{{ Graphique::court($valeur) }}</text>
    @endforeach

    @foreach ($serie as $i => $mois)
        @php $centre = $gauche + $pas * $i + $pas / 2; @endphp
        <rect x="{{ $centre - $barre - 1 }}" y="{{ $y($mois['entrees']) }}" width="{{ $barre }}" height="{{ $haut + $zone - $y($mois['entrees']) }}" rx="2" class="fill-emerald-700">
            <title>{{ $mois['libelle'] }} · entrées {{ \App\Support\Format::fcfa($mois['entrees']) }}</title>
        </rect>
        <rect x="{{ $centre + 1 }}" y="{{ $y($mois['sorties']) }}" width="{{ $barre }}" height="{{ $haut + $zone - $y($mois['sorties']) }}" rx="2" class="fill-amber-500">
            <title>{{ $mois['libelle'] }} · sorties {{ \App\Support\Format::fcfa($mois['sorties']) }}</title>
        </rect>
        <text x="{{ $centre }}" y="{{ $hauteur - 8 }}" text-anchor="middle" class="fill-stone-600" font-size="12">{{ $mois['libelle'] }}</text>
    @endforeach
</svg>
