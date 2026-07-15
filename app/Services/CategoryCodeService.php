<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryCodeService
{
    public function generate(string $name, ?int $ignoreId = null): string
    {
        $normalized = (string) Str::of($name)->ascii()->upper()->replaceMatches('/[^A-Z0-9 ]+/', ' ')->squish();
        $base = match ($normalized) {
            'RELATIONS INTERNATIONALES' => 'RI', 'DROIT COMMERCIAL' => 'DRC', 'DROIT CIVIL' => 'DRCI',
            default => substr(collect(explode(' ', $normalized))->filter(fn ($word) => $word !== '' && ! in_array($word, ['DE', 'DES', 'DU', 'LA', 'LE', 'LES', 'ET'], true))->map(fn ($word) => $word[0])->join(''), 0, 6) ?: 'GEN',
        };
        $code = $base;
        for ($suffix = 2; Category::withTrashed()->where('inventory_code', $code)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists(); $suffix++) {
            $code = substr($base, 0, 7).$suffix;
        }

        return $code;
    }
}
