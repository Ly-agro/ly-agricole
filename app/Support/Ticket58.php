<?php

namespace App\Support;

/**
 * Ticket pour imprimante thermique 58 mm (Xprinter, mini POS 58 mm) : 32 caractères par
 * ligne en police normale (384 points ÷ 12). On ne compose que du texte à largeur fixe :
 * c'est ce que ces imprimantes savent faire, et le rendu reste le même à l'écran.
 *
 * Même mise en page que l'appli terrain (terrain/src/lib/ticket.ts), qui imprime en
 * Bluetooth sans passer par le serveur.
 */
class Ticket58
{
    public const LARGEUR = 32;

    /** @var list<array{texte: string, gras: bool, centre: bool, grand: bool}> */
    private array $lignes = [];

    public function titre(string $texte): self
    {
        foreach ($this->couper($texte, intdiv(self::LARGEUR, 2)) as $l) {
            $this->lignes[] = ['texte' => $l, 'gras' => true, 'centre' => true, 'grand' => true];
        }

        return $this;
    }

    public function centre(string $texte, bool $gras = false): self
    {
        foreach ($this->couper($texte) as $l) {
            $this->lignes[] = ['texte' => $l, 'gras' => $gras, 'centre' => true, 'grand' => false];
        }

        return $this;
    }

    public function texte(string $texte, bool $gras = false): self
    {
        foreach ($this->couper($texte) as $l) {
            $this->lignes[] = ['texte' => $l, 'gras' => $gras, 'centre' => false, 'grand' => false];
        }

        return $this;
    }

    /** Libellé à gauche, valeur calée à droite ; la valeur passe dessous si trop longue. */
    public function paire(string $libelle, string $valeur, bool $gras = false): self
    {
        $place = self::LARGEUR - mb_strlen($valeur) - 1;
        if ($place < 8) {
            $this->texte($libelle, $gras);
            $this->lignes[] = ['texte' => self::aDroite($valeur), 'gras' => $gras, 'centre' => false, 'grand' => false];

            return $this;
        }
        $morceaux = $this->couper($libelle, $place);
        foreach (array_slice($morceaux, 0, -1) as $l) {
            $this->lignes[] = ['texte' => $l, 'gras' => $gras, 'centre' => false, 'grand' => false];
        }
        $dernier = (string) end($morceaux);
        $this->lignes[] = [
            'texte' => $dernier.str_repeat(' ', self::LARGEUR - mb_strlen($dernier) - mb_strlen($valeur)).$valeur,
            'gras' => $gras, 'centre' => false, 'grand' => false,
        ];

        return $this;
    }

    public function trait(string $motif = '-'): self
    {
        $this->lignes[] = ['texte' => str_repeat($motif, self::LARGEUR), 'gras' => false, 'centre' => false, 'grand' => false];

        return $this;
    }

    public function vide(): self
    {
        $this->lignes[] = ['texte' => '', 'gras' => false, 'centre' => false, 'grand' => false];

        return $this;
    }

    /** Ligne à signer : « Fournisseur : ____ ». */
    public function signature(string $qui): self
    {
        $this->vide()->vide();

        return $this->texte($qui.' : '.str_repeat('_', max(4, self::LARGEUR - mb_strlen($qui) - 3)));
    }

    /** @return list<array{texte: string, gras: bool, centre: bool, grand: bool}> */
    public function lignes(): array
    {
        return $this->lignes;
    }

    /** Texte brut (tests, aperçu) : une ligne de 32 caractères au plus par ligne. */
    public function enTexte(): string
    {
        return implode("\n", array_map(
            fn (array $l) => $l['centre'] ? self::centrer($l['texte'], $l['grand'] ? intdiv(self::LARGEUR, 2) : self::LARGEUR) : $l['texte'],
            $this->lignes,
        ));
    }

    private static function aDroite(string $texte): string
    {
        return str_repeat(' ', max(0, self::LARGEUR - mb_strlen($texte))).$texte;
    }

    private static function centrer(string $texte, int $largeur): string
    {
        return str_repeat(' ', intdiv(max(0, $largeur - mb_strlen($texte)), 2)).$texte;
    }

    /** @return list<string> mots gardés entiers ; un mot plus long que la ligne est coupé. */
    private function couper(string $texte, int $largeur = self::LARGEUR): array
    {
        $texte = trim((string) preg_replace('/\s+/u', ' ', $texte));
        if ($texte === '') {
            return [''];
        }

        $lignes = [];
        $courante = '';
        foreach (explode(' ', $texte) as $mot) {
            while (mb_strlen($mot) > $largeur) {
                if ($courante !== '') {
                    $lignes[] = $courante;
                    $courante = '';
                }
                $lignes[] = mb_substr($mot, 0, $largeur);
                $mot = mb_substr($mot, $largeur);
            }
            $essai = $courante === '' ? $mot : $courante.' '.$mot;
            if (mb_strlen($essai) <= $largeur) {
                $courante = $essai;
            } else {
                $lignes[] = $courante;
                $courante = $mot;
            }
        }
        $lignes[] = $courante;

        return $lignes;
    }
}
