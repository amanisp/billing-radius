<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            // Drop the old unique index on username
            $table->dropUnique('connections_username_unique');

            // Add composite unique index with a custom name
            $table->unique(['group_id', 'username'], 'unique_group_username');
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            // Drop the composite unique index by custom name
            $table->dropUnique('unique_group_username');

            // Restore the original unique constraint
            $table->unique('username', 'connections_username_unique');
        });
    }
};
