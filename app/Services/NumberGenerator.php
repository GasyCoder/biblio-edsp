<?php

namespace App\Services;

use App\Enums\NumberType;
use App\Models\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NumberGenerator
{
    public function next(NumberType $type, ?Carbon $date = null, ?string $categoryCode = null): string
    {
        $date ??= now();
        $scope = match ($type) {
            NumberType::Student, NumberType::LibraryCard => $date->format('Y'),
            NumberType::Visit, NumberType::Consultation, NumberType::Loan => $date->format('Ymd'),
            default => 'global',
        };
        $sequenceKey = $type === NumberType::LibraryCard ? NumberType::Student->value : $type->value;

        return DB::transaction(function () use ($type, $scope, $categoryCode, $sequenceKey): string {
            DB::table('number_sequences')->insertOrIgnore([
                'key' => $sequenceKey,
                'scope' => $scope,
                'current_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = NumberSequence::query()
                ->where('key', $sequenceKey)
                ->where('scope', $scope)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('current_value');
            $value = $sequence->fresh()->current_value;

            if ($type === NumberType::Student || $type === NumberType::LibraryCard) {
                return sprintf('%s-%s-%03d', $type->prefix(), substr($scope, -2), $value);
            }

            if ($type === NumberType::Copy) {
                return sprintf('EDSP-%s-%04d', $categoryCode ?: 'GEN', $value);
            }

            return $scope === 'global'
                ? sprintf('%s-%06d', $type->prefix(), $value)
                : sprintf('%s-%s-%06d', $type->prefix(), $scope, $value);
        }, 3);
    }
}
