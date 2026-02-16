<?php

namespace App\Http\Controllers\President;

use App\Http\Controllers\Commission\CommissionDossierController;

// President has same dossier access as commission, but role is stricter via routes.
// We reuse the same controller implementation by extending it.
class PresidentDossierController extends CommissionDossierController
{
}
