<?php

namespace App\Livewire\Referentiels;

use App\Models\Village;

/**
 * Liste déroulante des villages, avec leur zone (deux villages peuvent porter le même
 * nom dans deux zones).
 */
class OptionsVillages
{
    /** @return array<int, string> */
    public static function toutes(): array
    {
        return Village::query()->with('zone')->orderBy('nom')->get()
            ->mapWithKeys(fn (Village $v) => [
                $v->id => $v->nom.' ('.$v->zone->nom.')'.($v->actif ? '' : ' — désactivé'),
            ])->all();
    }
}
