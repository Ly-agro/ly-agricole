<?php

namespace App\Exceptions;

use LogicException;

/**
 * Tentative de modifier ou supprimer une ligne d'un registre immuable (D5).
 * Une erreur se corrige par contre-passation, jamais en réécrivant l'histoire.
 */
class RegistreImmuableException extends LogicException
{
    public static function pour(string $table, string $operation): self
    {
        return new self("Le registre « {$table} » est immuable : {$operation} interdite. Corriger par contre-passation.");
    }
}
