<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-user preferred admin UI locale (e.g. "en", "ro"). Optional —
 * only consulted by the plugin when configured with persist('user'). Defaults
 * to NULL so existing rows fall back to the session / panel default.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'locale')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 8)->nullable()->after('email')
                ->comment('Preferred admin UI locale (ISO 639-1, optionally with -XX region). NULL = use session/default.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'locale')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
