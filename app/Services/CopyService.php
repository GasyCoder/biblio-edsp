<?php

namespace App\Services;

use App\Enums\BarcodeSymbology;
use App\Enums\CopyCondition;
use App\Enums\CopyStatus;
use App\Enums\NumberType;
use App\Models\Copy;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CopyService
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Copy
    {
        return DB::transaction(function () use ($data): Copy {
            return Copy::query()->create([
                ...Arr::except($data, ['inventory_number', 'barcode_value']),
                'inventory_number' => $this->numbers->next(NumberType::Copy),
                'barcode_value' => 'EDSP:COPY:1:'.Str::ulid(),
                'barcode_symbology' => $data['barcode_symbology'] ?? BarcodeSymbology::Code128,
                'condition' => $data['condition'] ?? CopyCondition::Good,
                'status' => $data['status'] ?? CopyStatus::Available,
                'registered_at' => $data['registered_at'] ?? now(),
            ]);
        }, 3);
    }
}
