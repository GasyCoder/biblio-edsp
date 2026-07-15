<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'inventory_code')) {
            Schema::table('categories', fn (Blueprint $table) => $table->string('inventory_code', 10)->nullable()->after('name'));
        }

        $used = [];
        DB::table('categories')->orderBy('id')->get()->each(function (object $category) use (&$used) {
            $base = $this->categoryCode($category->name);
            $code = $base;
            for ($suffix = 2; in_array($code, $used, true); $suffix++) {
                $code = substr($base, 0, 7).$suffix;
            }
            $used[] = $code;
            DB::table('categories')->where('id', $category->id)->update(['inventory_code' => $code]);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->unique('inventory_code');
        });

        $copies = DB::table('copies')->leftJoin('books', 'books.id', '=', 'copies.book_id')->leftJoin('categories', 'categories.id', '=', 'books.category_id')->orderBy('copies.id')->get(['copies.id', 'categories.inventory_code']);
        foreach ($copies as $index => $copy) {
            DB::table('copies')->where('id', $copy->id)->update(['inventory_number' => 'TMP-'.$copy->id]);
        }
        foreach ($copies as $index => $copy) {
            DB::table('copies')->where('id', $copy->id)->update(['inventory_number' => sprintf('EDSP-%s-%04d', $copy->inventory_code ?: 'GEN', $index + 1)]);
        }

        DB::table('number_sequences')->updateOrInsert(['key' => 'copy', 'scope' => 'global'], ['current_value' => $copies->count(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        $copies = DB::table('copies')->orderBy('id')->get(['id']);
        foreach ($copies as $copy) {
            DB::table('copies')->where('id', $copy->id)->update(['inventory_number' => 'TMP-'.$copy->id]);
        }
        foreach ($copies as $index => $copy) {
            DB::table('copies')->where('id', $copy->id)->update(['inventory_number' => sprintf('EDSP-LIV-%06d', $index + 1)]);
        }
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['inventory_code']);
            $table->dropColumn('inventory_code');
        });
    }

    private function categoryCode(string $name): string
    {
        $normalized = (string) Str::of($name)->ascii()->upper()->replaceMatches('/[^A-Z0-9 ]+/', ' ')->squish();

        return match ($normalized) {
            'RELATIONS INTERNATIONALES' => 'RI',
            'DROIT COMMERCIAL' => 'DRC',
            'DROIT CIVIL' => 'DRCI',
            default => substr(collect(explode(' ', $normalized))->filter(fn ($word) => $word !== '' && ! in_array($word, ['DE', 'DES', 'DU', 'LA', 'LE', 'LES', 'ET'], true))->map(fn ($word) => $word[0])->join(''), 0, 6) ?: 'GEN',
        };
    }
};
