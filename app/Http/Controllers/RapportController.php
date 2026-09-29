<?php

namespace App\Http\Controllers;

use App\Models\Campagne;
use App\Services\Rapports;
use App\Support\Tableau;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapports de la direction : à l'écran, en PDF (à signer / archiver) et en fichier pour
 * Excel (CSV ; séparateur « ; », UTF-8 avec BOM : Excel en français l'ouvre tel quel).
 */
class RapportController extends Controller
{
    public const RAPPORTS = [
        'prets' => 'Portefeuille de prêts',
        'stock' => 'Stock par lot et magasin',
        'caisses' => 'Caisses et comptes',
        'ecarts' => 'Écarts de poids',
        'alertes' => 'Alertes',
    ];

    public function index(Rapports $rapports): View
    {
        Gate::authorize('voir-rapports');

        return view('rapports.index', ['synthese' => $rapports->synthese(), 'liste' => self::RAPPORTS]);
    }

    public function afficher(Request $request, string $rapport, Rapports $rapports): View|Response|StreamedResponse
    {
        Gate::authorize('voir-rapports');

        $campagneId = $request->integer('campagne') ?: null;
        $tableau = match ($rapport) {
            'prets' => $rapports->portefeuille($campagneId),
            'stock' => $rapports->stock(),
            'caisses' => $rapports->caisses(),
            'ecarts' => $rapports->ecarts(),
            'alertes' => $rapports->alertes(),
            default => abort(404),
        };
        $nomFichier = 'ly-agricole-'.$rapport.'-'.now()->format('Y-m-d');

        return match ($request->query('format')) {
            'pdf' => Pdf::loadView('rapports.pdf', ['tableau' => $tableau])
                ->setPaper('a4', count($tableau->colonnes) > 6 ? 'landscape' : 'portrait')
                ->setOption('isFontSubsettingEnabled', true)
                ->stream($nomFichier.'.pdf'),
            'excel' => $this->csv($tableau, $nomFichier.'.csv'),
            default => view('rapports.tableau', [
                'tableau' => $tableau,
                'rapport' => $rapport,
                'liste' => self::RAPPORTS,
                'campagnes' => $rapport === 'prets' ? Campagne::query()->orderByDesc('debut')->get() : collect(),
                'campagneId' => $campagneId,
            ]),
        };
    }

    private function csv(Tableau $tableau, string $nom): StreamedResponse
    {
        return response()->streamDownload(function () use ($tableau) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\u{FEFF}");
            fputcsv($sortie, [$tableau->titre.' — LY AGRICOLE — '.now()->format('d/m/Y H:i')], ';', '"', '');
            fputcsv($sortie, array_map(fn ($c) => $c[0].($c[1] === Tableau::KG ? ' (kg)' : ($c[1] === Tableau::FCFA ? ' (FCFA)' : ($c[1] === Tableau::POUR_MILLE ? ' (%)' : ''))), $tableau->colonnes), ';', '"', '');
            foreach ([...$tableau->lignes, ...($tableau->totaux === null ? [] : [$tableau->totaux])] as $ligne) {
                fputcsv($sortie, array_map(fn ($v, $c) => Tableau::brut($v, $c[1]), $ligne, $tableau->colonnes), ';', '"', '');
            }
            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
