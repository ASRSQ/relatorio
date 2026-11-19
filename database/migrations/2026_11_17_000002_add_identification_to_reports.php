<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estagio_relatorios', function (Blueprint $table) {
            $table->string('serie', 100)->nullable();
            foreach (['empresa', 'escola'] as $prefixo) {
                $table->string($prefixo.'_endereco')->nullable();
                $table->string($prefixo.'_numero', 30)->nullable();
                $table->string($prefixo.'_bairro', 120)->nullable();
                $table->string($prefixo.'_cidade', 120)->nullable();
                $table->string($prefixo.'_telefone', 30)->nullable();
                $table->string($prefixo.'_email')->nullable();
            }
            $table->text('referencias')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('estagio_relatorios', function (Blueprint $table) {
            $columns = ['serie', 'referencias'];
            foreach (['empresa', 'escola'] as $prefixo) {
                foreach (['endereco', 'numero', 'bairro', 'cidade', 'telefone', 'email'] as $campo) {
                    $columns[] = $prefixo.'_'.$campo;
                }
            }
            $table->dropColumn($columns);
        });
    }
};
