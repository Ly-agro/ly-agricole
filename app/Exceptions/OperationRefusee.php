<?php

namespace App\Exceptions;

use DomainException;

/**
 * Opération métier refusée (solde insuffisant, auteur qui valide sa propre saisie…).
 * Le message est destiné à l'utilisateur, en français.
 */
class OperationRefusee extends DomainException {}
