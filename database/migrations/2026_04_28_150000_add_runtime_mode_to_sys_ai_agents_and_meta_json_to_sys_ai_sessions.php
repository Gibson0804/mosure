<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sys_ai_agents') && ! Schema::hasColumn('sys_ai_agents', 'runtime_mode')) {
            Schema::table('sys_ai_agents', function (Blueprint $table) {
                $table->string('runtime_mode', 50)
                    ->default('general')
                    ->after('enabled')
                    ->comment('custom agent runtime mode: general|frontend_page_developer');
            });
        }

        if (Schema::hasTable('sys_ai_sessions') && ! Schema::hasColumn('sys_ai_sessions', 'meta_json')) {
            Schema::table('sys_ai_sessions', function (Blueprint $table) {
                $table->json('meta_json')
                    ->nullable()
                    ->after('context_token_count')
                    ->comment('session runtime metadata such as current page focus');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sys_ai_agents') && Schema::hasColumn('sys_ai_agents', 'runtime_mode')) {
            Schema::table('sys_ai_agents', function (Blueprint $table) {
                $table->dropColumn('runtime_mode');
            });
        }

        if (Schema::hasTable('sys_ai_sessions') && Schema::hasColumn('sys_ai_sessions', 'meta_json')) {
            Schema::table('sys_ai_sessions', function (Blueprint $table) {
                $table->dropColumn('meta_json');
            });
        }
    }
};
