@php($f = \App\Support\Format::class)
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bon d'achat {{ $achat->reference }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; font-size: 9pt; }
        .entete { border-bottom: 0.5mm solid #065f46; padding-bottom: 3mm; margin-bottom: 4mm; }
        .marque { color: #065f46; font-weight: bold; font-size: 13pt; }
        .devise { color: #78716c; font-size: 7pt; }
        .entete td { vertical-align: bottom; padding: 0; }
        .titre { text-align: right; }
        .titre strong { font-size: 11pt; }
        table { width: 100%; border-collapse: collapse; }
        .infos td { padding: 1mm 0; vertical-align: top; }
        .infos td.cle { color: #78716c; width: 32mm; }
        .alerte { margin: 3mm 0; padding: 2mm 3mm; border: 0.4mm solid #b91c1c; color: #b91c1c; font-weight: bold; }
        .bloc { margin-top: 4mm; }
        .bloc th { text-align: left; color: #57534e; font-weight: normal; border-bottom: 0.3mm solid #a8a29e; padding: 1.5mm 1mm; }
        .bloc td { border-bottom: 0.2mm solid #e7e5e4; padding: 1.5mm 1mm; }
        .nombre { text-align: right; white-space: nowrap; }
        .paye td { font-weight: bold; font-size: 10.5pt; border-top: 0.4mm solid #1c1917; border-bottom: none; padding-top: 2mm; }
        .mention { margin-top: 5mm; font-size: 8pt; color: #44403c; }
        .signatures { margin-top: 8mm; }
        .signatures td { width: 50%; padding-top: 16mm; border-bottom: 0.3mm solid #a8a29e; color: #78716c; font-size: 8pt; vertical-align: bottom; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td><div class="marque">LY AGRICOLE</div><div class="devise">Cultiver – Élever – Durer</div></td>
            <td class="titre"><strong>Bon d'achat</strong><br>N° {{ $achat->reference }}<br>du {{ $achat->date_achat->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    @if ($mention)
        <div class="alerte">{{ $mention }}</div>
    @endif

    <table class="infos">
        <tr><td class="cle">Fournisseur</td><td><strong>{{ $achat->nomFournisseur() }}</strong> ({{ $achat->fournisseur_type->libelle() }})@if ($achat->producteur) — carte {{ $achat->producteur->code }}, {{ $achat->producteur->village->nom }}@endif</td></tr>
        <tr><td class="cle">Produit</td><td>{{ $achat->campagne->produit->nom }} — campagne {{ $achat->campagne->code }} — lot {{ $achat->lot->code }}</td></tr>
        <tr><td class="cle">Acheteur</td><td>{{ $achat->auteur->nom }}</td></tr>
    </table>

    <table class="bloc">
        <thead><tr><th>Pesée et qualité</th><th class="nombre"></th></tr></thead>
        <tbody>
            <tr><td>Poids brut</td><td class="nombre">{{ $f::kg($achat->poids_brut_g) }}</td></tr>
            <tr><td>Tare (sacs)</td><td class="nombre">{{ $f::kg($achat->tare_g) }}</td></tr>
            <tr><td><strong>Poids net</strong></td><td class="nombre"><strong>{{ $f::kg($achat->poids_net_g) }}</strong></td></tr>
            <tr><td>Humidité</td><td class="nombre">{{ $achat->humidite_pour_mille === null ? '—' : intdiv($achat->humidite_pour_mille, 10).','.($achat->humidite_pour_mille % 10).' %' }}</td></tr>
            <tr><td>KOR (lbs / sac de 80 kg)</td><td class="nombre">{{ $achat->kor_centieme_lbs === null ? '—' : intdiv($achat->kor_centieme_lbs, 100).','.str_pad((string) ($achat->kor_centieme_lbs % 100), 2, '0', STR_PAD_LEFT) }}</td></tr>
            <tr><td>Grainage (noix / kg)</td><td class="nombre">{{ $achat->grainage_noix_kg ?? '—' }}</td></tr>
        </tbody>
    </table>

    <table class="bloc">
        <thead><tr><th>Règlement</th><th class="nombre"></th></tr></thead>
        <tbody>
            <tr><td>{{ $f::kg($achat->poids_net_g) }} × {{ $f::fcfa($achat->prix_kg_fcfa) }} / kg</td><td class="nombre">{{ $f::fcfa($achat->montant_fcfa) }}</td></tr>
            @if ($achat->pret)
                <tr><td>Retenu pour le prêt {{ $achat->pret->reference }} : {{ $f::kg($achat->grammes_rembourses) }}</td><td class="nombre">− {{ $f::fcfa($valeurRetenue) }}</td></tr>
            @endif
            <tr class="paye"><td>{{ $achat->statut->value === 'valide' ? 'Payé en espèces' : 'À payer en espèces après validation' }}</td><td class="nombre">{{ $f::fcfa($achat->montant_especes_fcfa) }}</td></tr>
        </tbody>
    </table>

    <p class="mention">
        @if ($achat->pret && $restantDu !== null)
            Restant dû sur le prêt {{ $achat->pret->reference }} au {{ now()->format('d/m/Y') }} : <strong>{{ $f::fcfa($restantDu) }}</strong>.<br>
        @endif
        Le fournisseur reconnaît avoir livré le poids net ci-dessus et reçu la somme payée en espèces.
        Une confirmation est envoyée par SMS au numéro du producteur.
    </p>

    <table class="signatures">
        <tr><td>Signature ou empreinte du fournisseur</td><td style="padding-left: 8mm;">L'acheteur pour LY AGRICOLE</td></tr>
    </table>
</body>
</html>
