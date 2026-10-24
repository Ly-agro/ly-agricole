@php($f = \App\Support\Format::class)
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Point d'étape — campagne {{ $campagne->code }}</title>
    <style>
        @page { margin: 12mm 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1c1917; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; }
        .entete { border-bottom: 0.5mm solid #065f46; padding-bottom: 2mm; margin-bottom: 4mm; }
        .entete td { vertical-align: bottom; padding: 0; }
        .marque { color: #065f46; font-weight: bold; font-size: 15pt; }
        .devise { color: #78716c; font-size: 7.5pt; }
        .titre { text-align: right; }
        .titre strong { font-size: 12pt; }
        h2 { font-size: 10.5pt; color: #065f46; margin: 4mm 0 1mm 0; border-bottom: 0.3mm solid #a8a29e; padding-bottom: 1mm; }
        td { padding: 1mm 1mm; border-bottom: 0.2mm solid #e7e5e4; vertical-align: top; }
        td.nombre { text-align: right; white-space: nowrap; }
        td.sous { padding-left: 6mm; color: #57534e; }
        tr.total td { font-weight: bold; border-top: 0.4mm solid #1c1917; border-bottom: none; }
        .evenements { border: 0.3mm solid #d6d3d1; padding: 3mm; white-space: pre-wrap; }
        .vide { color: #78716c; font-style: italic; }
        .mention { margin-top: 5mm; font-size: 8pt; color: #44403c; border-top: 0.3mm solid #d6d3d1; padding-top: 2mm; }
        .signature { margin-top: 4mm; }
        .signature td { border: none; padding-top: 9mm; width: 50%; color: #78716c; font-size: 8pt; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td><div class="marque">LY AGRICOLE</div><div class="devise">Cultiver – Élever – Durer</div></td>
            <td class="titre"><strong>Point d'étape de la campagne</strong><br>{{ $campagne->produit->nom }} — {{ $campagne->code }}<br>État au {{ $etat_au->format('d/m/Y') }}</td>
        </tr>
    </table>

    <p>Note d'avancement adressée aux investisseurs au titre de l'article 18.1 du contrat de campagne.</p>

    <h2>1. Fonds de la campagne</h2>
    <table>
        <tr><td>Montant collecté auprès de {{ $fonds['nb_investisseurs'] }} investisseur{{ $fonds['nb_investisseurs'] > 1 ? 's' : '' }}</td><td class="nombre">{{ $f::fcfa($fonds['investisseurs']) }}</td></tr>
        <tr><td>Apport propre de LY AGRICOLE</td><td class="nombre">{{ $f::fcfa($fonds['ly']) }}</td></tr>
        <tr class="total"><td>Fonds de la campagne</td><td class="nombre">{{ $f::fcfa($fonds['total']) }}</td></tr>
    </table>

    <h2>2. Volumes achetés et vendus</h2>
    <table>
        <tr><td>Acheté ({{ $volumes['nb_achats'] }} {{ $volumes['nb_achats'] > 1 ? 'achats validés' : 'achat validé' }})</td><td class="nombre">{{ $f::kg($volumes['achete_g']) }}</td></tr>
        <tr><td>Vendu ({{ $volumes['nb_ventes'] }} {{ $volumes['nb_ventes'] > 1 ? 'ventes validées' : 'vente validée' }})</td><td class="nombre">{{ $f::kg($volumes['vendu_g']) }}</td></tr>
        <tr class="total"><td>En stock</td><td class="nombre">{{ $f::kg($volumes['stock_g']) }}</td></tr>
    </table>

    <h2>3. Montants engagés</h2>
    <table>
        <tr><td>Achats (coût d'achat)</td><td class="nombre">{{ $f::fcfa($engage['achats']) }}</td></tr>
        @foreach ($engage['depenses'] as $d)
            <tr><td class="sous">{{ $d['categorie'] }}</td><td class="nombre">{{ $f::fcfa($d['montant']) }}</td></tr>
        @endforeach
        <tr class="total"><td>Total achats et charges</td><td class="nombre">{{ $f::fcfa($engage['achats'] + $engage['total_depenses']) }}</td></tr>
    </table>
    <table>
        <tr><td>Avances de campagne versées aux producteurs</td><td class="nombre">{{ $f::fcfa($engage['avances_decaissees']) }}</td></tr>
        <tr><td class="sous">dont pas encore remboursées</td><td class="nombre">{{ $f::fcfa($engage['avances_non_remboursees']) }}</td></tr>
    </table>

    <h2>4. Ventes et encaissements</h2>
    <table>
        <tr><td>Ventes validées</td><td class="nombre">{{ $f::fcfa($ventes['facture']) }}</td></tr>
        <tr><td>Encaissé</td><td class="nombre">{{ $f::fcfa($ventes['encaisse']) }}</td></tr>
        <tr class="total"><td>Reste à encaisser</td><td class="nombre">{{ $f::fcfa($ventes['reste']) }}</td></tr>
    </table>

    <h2>5. Trésorerie disponible</h2>
    @if ($tresorerie['comptes'] === [])
        <p class="vide">Aucun compte n'est rattaché à cette campagne.</p>
    @else
        <table>
            @foreach ($tresorerie['comptes'] as $c)
                <tr><td>{{ $c['nom'] }}</td><td class="nombre">{{ $f::fcfa($c['solde']) }}</td></tr>
            @endforeach
            <tr class="total"><td>Total</td><td class="nombre">{{ $f::fcfa($tresorerie['total']) }}</td></tr>
        </table>
    @endif

    <h2>6. Principaux événements</h2>
    @if ($evenements === '')
        <p class="vide">Aucun événement particulier signalé.</p>
    @else
        <div class="evenements">{{ $evenements }}</div>
    @endif

    <p class="mention">
        Chiffres relevés dans les registres de LY AGRICOLE à la date d'édition ; ils évoluent avec chaque opération.
        Cette note ne présente ni résultat, ni quote-part, ni prévision : elle n'est pas le rapport final prévu à
        l'article 18.2 et n'engage aucun paiement.
    </p>

    <table class="signature">
        <tr><td>Fait à ______________________, le ____ / ____ / ________</td><td>Pour LY AGRICOLE — nom, qualité, signature</td></tr>
    </table>
</body>
</html>
