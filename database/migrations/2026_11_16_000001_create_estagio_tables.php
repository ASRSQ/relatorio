<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estagio_alunos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->char('cpf_hash', 64)->unique();
            $table->date('nascimento');
            $table->string('escola');
            $table->string('curso');
            $table->string('ano_letivo', 10);
            $table->string('concedente');
            $table->date('inicio')->nullable();
            $table->date('termino')->nullable();
            $table->unsignedSmallInteger('carga_horaria')->default(300);
            $table->string('orientador');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
        Schema::create('estagio_relatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->unique()->constrained('estagio_alunos')->cascadeOnDelete();
            $table->longText('introducao')->nullable();
            $table->longText('atividades')->nullable();
            $table->longText('conclusao')->nullable();
            $table->string('local', 120)->nullable();
            $table->date('data_relatorio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estagio_relatorios');
        Schema::dropIfExists('estagio_alunos');
    }
};
