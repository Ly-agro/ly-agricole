<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titre }} — ticket 58 mm</title>
    <style>
        /* Papier 58 mm : zone imprimable d'environ 48 mm, 32 caractères par ligne. */
        @page { size: 58mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e7e5e4; font-family: Consolas, "Courier New", monospace; color: #000; }
        .barre { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: center; padding: 12px 16px; font-family: system-ui, sans-serif; font-size: 14px; }
        .barre button, .barre a { border-radius: 6px; padding: 8px 14px; font-size: 14px; text-decoration: none; cursor: pointer; }
        .barre button { background: #047857; color: #fff; border: 0; font-weight: 600; }
        .barre a { color: #1c1917; border: 1px solid #a8a29e; background: #fff; }
        .aide { width: 100%; text-align: center; color: #57534e; font-size: 12px; }
        .ticket { width: 58mm; margin: 0 auto 24px; padding: 3mm 5mm; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
        .l { white-space: pre; font-size: 2.6mm; line-height: 1.25; overflow: hidden; }
        .g { font-weight: 700; }
        .c { text-align: center; }
        .grand { font-size: 5.2mm; line-height: 1.2; }
        @media print {
            body { background: #fff; }
            .barre { display: none; }
            .ticket { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="barre">
        <button type="button" onclick="window.print()">Imprimer (58 mm)</button>
        <a href="{{ $pdf }}">Version A4 / A5 (PDF)</a>
        <p class="aide">Choisir l'imprimante 58 mm (Xprinter, mini POS) ; papier 58 mm, marges « aucune ». Pour la Phomemo M832, prendre la version PDF.</p>
    </div>
    <div class="ticket">
        @foreach ($lignes as $l)
            <div @class(['l', 'g' => $l['gras'], 'c' => $l['centre'], 'grand' => $l['grand']])>{{ $l['texte'] === '' ? ' ' : $l['texte'] }}</div>
        @endforeach
    </div>
</body>
</html>
