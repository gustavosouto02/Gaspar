<?php

use App\Services\CodeGeneratorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const NAME = 'Interno';

    private const EMAIL = 'interno@gaspar.local';

    public function up(): void
    {
        if (DB::table('clients')->where('name', self::NAME)->exists()) {
            return;
        }

        DB::table('clients')->insert([
            'id' => (string) Str::uuid7(),
            'code' => app(CodeGeneratorService::class)->generate('clients', 'code', 'CLI'),
            'name' => self::NAME,
            'email' => self::EMAIL,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('clients')
            ->where('name', self::NAME)
            ->where('email', self::EMAIL)
            ->value('id');

        if ($id
            && ! DB::table('demands')->where('client_id', $id)->exists()
            && ! DB::table('projects')->where('client_id', $id)->exists()) {
            DB::table('clients')->where('id', $id)->delete();
        }
    }
};
