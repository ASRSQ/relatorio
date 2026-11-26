<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('turmas', function (Blueprint $table) {
            $table->unsignedSmallInteger('carga_horaria')->default(300);
        });
        // Preserve all student values; use the most frequent existing value as the class default.
        foreach (DB::table('turmas')->select('id')->get() as $turma) {
            $valor = DB::table('estagio_alunos')->where('turma_id', $turma->id)
                ->select('carga_horaria')->selectRaw('COUNT(*) as total')
                ->groupBy('carga_horaria')->orderByDesc('total')->orderBy('carga_horaria')->value('carga_horaria');
            if ($valor !== null) DB::table('turmas')->where('id', $turma->id)->update(['carga_horaria' => $valor]);
        }
        Schema::create('historico_carga_horaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('carga_anterior');
            $table->unsignedSmallInteger('carga_nova');
            $table->unsignedInteger('alunos_afetados');
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('historico_carga_horaria');
        Schema::table('turmas', fn (Blueprint $table) => $table->dropColumn('carga_horaria'));
    }
};
