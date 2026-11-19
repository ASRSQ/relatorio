<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) { $table->boolean('ativo')->default(true); $table->boolean('is_professor')->default(false); });
        Schema::create('turmas', function (Blueprint $table) {
            $table->id(); $table->string('nome'); $table->string('curso'); $table->string('serie')->nullable();
            $table->string('ano_letivo', 10); $table->string('turno')->nullable(); $table->boolean('ativa')->default(true); $table->timestamps();
        });
        Schema::create('professor_turma', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->primary(['user_id', 'turma_id']);
        });
        Schema::create('concedentes', function (Blueprint $table) {
            $table->id(); $table->string('nome'); $table->string('nome_fantasia')->nullable();
            $table->string('documento', 14)->nullable()->unique(); $table->string('endereco')->nullable(); $table->string('numero', 30)->nullable();
            $table->string('bairro')->nullable(); $table->string('cidade')->nullable(); $table->string('estado', 2)->nullable();
            $table->string('telefone', 30)->nullable(); $table->string('email')->nullable(); $table->string('responsavel')->nullable();
            $table->boolean('ativa')->default(true); $table->timestamps();
        });
        Schema::table('estagio_alunos', function (Blueprint $table) {
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->nullOnDelete();
            $table->foreignId('concedente_id')->nullable()->constrained('concedentes')->nullOnDelete();
            $table->string('matricula')->nullable();
        });
        Schema::table('estagio_relatorios', fn (Blueprint $table) => $table->json('concedente_snapshot')->nullable());
        DB::table('estagio_relatorios')->join('estagio_alunos', 'estagio_alunos.id', '=', 'estagio_relatorios.aluno_id')
            ->whereNotNull('estagio_relatorios.concluido_em')
            ->select('estagio_relatorios.id', 'estagio_alunos.concedente', 'estagio_relatorios.empresa_endereco', 'estagio_relatorios.empresa_numero', 'estagio_relatorios.empresa_bairro', 'estagio_relatorios.empresa_cidade', 'estagio_relatorios.empresa_telefone', 'estagio_relatorios.empresa_email')
            ->orderBy('estagio_relatorios.id')->get()->each(function ($relatorio) {
                DB::table('estagio_relatorios')->where('id', $relatorio->id)->update(['concedente_snapshot' => json_encode([
                    'nome' => $relatorio->concedente, 'endereco' => $relatorio->empresa_endereco, 'numero' => $relatorio->empresa_numero,
                    'bairro' => $relatorio->empresa_bairro, 'cidade' => $relatorio->empresa_cidade, 'estado' => null,
                    'telefone' => $relatorio->empresa_telefone, 'email' => $relatorio->empresa_email,
                ], JSON_UNESCAPED_UNICODE)]);
            });
        Schema::create('solicitacoes_concedente', function (Blueprint $table) {
            $table->id(); $table->foreignId('aluno_id')->constrained('estagio_alunos')->cascadeOnDelete();
            $table->foreignId('concedente_id')->nullable()->constrained('concedentes')->nullOnDelete();
            $table->json('dados')->nullable(); $table->string('situacao')->default('pendente');
            $table->text('justificativa')->nullable(); $table->foreignId('decidido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidido_em')->nullable(); $table->timestamps();
            $table->index(['situacao', 'aluno_id']);
        });
        DB::table('estagio_alunos')->whereNotNull('turma')->where('turma', '!=', '')->orderBy('id')->chunkById(200, function ($alunos) {
            foreach ($alunos as $aluno) {
                $key = ['nome' => $aluno->turma, 'curso' => $aluno->curso, 'ano_letivo' => $aluno->ano_letivo];
                $id = DB::table('turmas')->where($key)->value('id');
                if (! $id) $id = DB::table('turmas')->insertGetId([...$key, 'ativa' => true, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('estagio_alunos')->where('id', $aluno->id)->update(['turma_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_concedente');
        Schema::table('estagio_relatorios', fn (Blueprint $table) => $table->dropColumn('concedente_snapshot'));
        Schema::table('estagio_alunos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('turma_id'); $table->dropConstrainedForeignId('concedente_id'); $table->dropColumn('matricula');
        });
        Schema::dropIfExists('professor_turma'); Schema::dropIfExists('concedentes'); Schema::dropIfExists('turmas');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['ativo', 'is_professor']));
    }
};
