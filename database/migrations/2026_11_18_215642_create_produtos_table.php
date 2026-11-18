<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    #preenchendo a tabela produtos com os campos: id, nome, categoria, garantia_meses, preco, estoque, vendedor_id (foreign key para a tabela users) e timestamps
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
        $table->id();
        $table->string('nome');
        $table->string('categoria');
        $table->integer('garantia_meses');
        $table->decimal('preco', 8, 2);
        $table->integer('estoque');
        $table->foreignId('vendedor_id')->constrained('users');
        $table->timestamps();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
