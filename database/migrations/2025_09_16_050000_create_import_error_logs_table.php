<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateImportErrorLogsTable extends Migration
{
    public function up()
    {
        Schema::create('import_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('import_batch_id')->nullable()->index();
            $table->integer('row_number')->nullable();
            $table->string('username')->nullable()->index();
            $table->string('error_type', 50)->index();
            $table->text('error_message');
            $table->json('row_data')->nullable();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->json('additional_data')->nullable();
            $table->boolean('resolved')->default(false)->index();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            // Composite index untuk query yang sering digunakan
            $table->index(['import_batch_id', 'resolved']);
            $table->index(['group_id', 'error_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('import_error_logs');
    }
}
