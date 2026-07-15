<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')->orderBy('id')->each(function (object $student) {
            if (preg_match('/^EDSP-ETU-(\d{4})-(\d+)$/', $student->registration_number, $matches)) {
                DB::table('students')->where('id', $student->id)->update([
                    'registration_number' => sprintf('ETU-%s-%03d', substr($matches[1], -2), (int) $matches[2]),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('students')->orderBy('id')->each(function (object $student) {
            if (preg_match('/^ETU-(\d{2})-(\d+)$/', $student->registration_number, $matches)) {
                DB::table('students')->where('id', $student->id)->update([
                    'registration_number' => sprintf('EDSP-ETU-20%s-%06d', $matches[1], (int) $matches[2]),
                ]);
            }
        });
    }
};
