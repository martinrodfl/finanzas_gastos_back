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
        Schema::create('movimientos', function (Blueprint $table) {
            $table->integer('id', true);
            $table->date('fecha')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('documento', 50)->nullable();
            $table->text('asunto')->nullable();
            $table->string('dependencia', 100)->nullable();
            $table->decimal('debito', 12)->nullable();
            $table->decimal('credito', 12)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->string('categoria_manual', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos');
    }
};
