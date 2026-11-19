<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::table('estagio_alunos', fn (Blueprint $table) => $table->string('turma', 100)->nullable());
        Schema::table('estagio_relatorios', function (Blueprint $table) {
            $table->timestamp('concluido_em')->nullable();
            $table->unsignedInteger('versao')->default(0);
        });
        DB::table('estagio_relatorios')->where(function ($query) {
            foreach (['introducao', 'atividades', 'conclusao'] as $field) {
                $query->orWhere(function ($q) use ($field) {
                    $q->whereNotNull($field)->where($field, '!=', '');
                });
            }
        })->update(['versao' => 1]);
    }

    public function down(): void
    {
        Schema::table('estagio_relatorios', fn (Blueprint $table) => $table->dropColumn(['concluido_em', 'versao']));
        Schema::table('estagio_alunos', fn (Blueprint $table) => $table->dropColumn('turma'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
