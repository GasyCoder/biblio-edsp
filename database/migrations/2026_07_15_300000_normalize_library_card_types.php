<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('student_cards')->where('type', 'student')->update(['type' => 'library']);
    }

    public function down(): void {}
};
