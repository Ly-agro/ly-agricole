<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $numero }}</title>
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
        .lignes { margin-top: 5mm; }
        .lignes th { text-align: left; color: #57534e; font-weight: normal; border-bottom: 0.3mm solid #a8a29e; padding: 1.5mm 1mm; }
        .lignes td { border-bottom: 0.2mm solid #e7e5e4; padding: 2mm 1mm; }
        .nombre { text-align: right; white-space: nowrap; }
        .totaux { margin-top: 4mm; width: 70mm; margin-left: auto; }
        .totaux td { padding: 1mm; }
        .totaux .du td { font-weight: bold; font-size: 10.5pt; border-top: 0.4mm solid #1c1917; padding-top: 2mm; }
        .mention { margin-top: 5mm; font-size: 8pt; color: #44403c; }
        .signatures { margin-top: 10mm; }
        .signatures td { width: 50%; padding-top: 16mm; border-bottom: 0.3mm solid #a8a29e; color: #78716c; font-size: 8pt; vertical-align: bottom; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td><div class="marque">LY AGRICOLE</div><div class="devise">Cultiver – Élever – Durer</div></td>
            <td class="titre"><strong>Reçu de remise</strong><br>N° {{ $numero }}<br>du {{ $date }}</td>
        </tr>
    </table>

    <table class="infos">
        <tr><td class="cle">Producteur</td><td><strong>{{ $pret->producteur->nomComplet() }}</strong> — carte {{ $pret->producteur->code }}, {{ $pret->producteur->village->nom }}</td></tr>
        <tr><td class="cle">Prêt</td><td>{{ $pret->reference }} — {{ \App\Support\Format::fcfa($pret->montant_fcfa) }} — {{ $pret->forme->libelle() }} — campagne {{ $pret->campagne->produit->nom }} {{ $pret->campagne->code }}</td></tr>
        <tr><td class="cle">Échéance</td><td>{{ $pret->echeance->format('d/m/Y') }}</td></tr>
    </table>

    <table class="lignes">
        <thead>
            <tr><th>Remis ce jour</th><th class="nombre">Quantité</th><th class="nombre">Prix unitaire</th><th class="nombre">Valeur</th></tr>
        </thead>
        <tbody>
            @foreach ($lignes as $ligne)
                <tr>
                    <td>{{ $ligne['libelle'] }}</td>
                    <td class="nombre">{{ $ligne['quantite'] ?? '—' }}</td>
                    <td class="nombre">{{ $ligne['prix'] === null ? '—' : \App\Support\Format::fcfa($ligne['prix']) }}</td>
                    <td class="nombre">{{ \App\Support\Format::fcfa($ligne['valeur']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totaux">
        <tr><td>Total remis à ce jour</td><td class="nombre">{{ \App\Support\Format::fcfa($remis) }}</td></tr>
        <tr class="du"><td>Restant dû</td><td class="nombre">{{ \App\Support\Format::fcfa($restantDu) }}</td></tr>
    </table>

    <p class="mention">
        Totaux calculés le {{ now()->format('d/m/Y') }}. Le producteur reconnaît avoir reçu ce qui est inscrit ci-dessus au titre
        de son prêt de campagne. Aucun intérêt n'est appliqué.
    </p>

    <table class="signatures">
        <tr><td>Signature ou empreinte du producteur</td><td style="padding-left: 8mm;">Pour LY AGRICOLE (nom et signature)</td></tr>
    </table>
</body>
</html>
