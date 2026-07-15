<?php

namespace App\Services;

use App\Enums\NumberType;
use App\Models\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NumberGenerator
{
    public function next(NumberType $type, ?Carbon $date = null): string
    {
        $date ??= now();
        $scope = $type === NumberType::Student ? $date->format('Y') : 'global';

        return DB::transaction(function () use ($type, $scope): string {
            DB::table('number_sequences')->insertOrIgnore([
                'key' => $type->value,
                'scope' => $scope,
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = NumberSequence::query()
                ->where('key', $type->value)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('current_value');
            $value = $sequence->fresh()->current_value;

            return $type === NumberType::Student
                ? sprintf('%s-%s-%06d', $type->prefix(), $scope, $value)
                : sprintf('%s-%06d', $type->prefix(), $value);
        }, 3);
    }
}
