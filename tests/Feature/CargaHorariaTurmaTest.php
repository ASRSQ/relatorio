<?php
namespace Tests\Feature;

use App\Models\{EstagioAluno, Turma, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CargaHorariaTurmaTest extends TestCase
{
    use RefreshDatabase;

    private function turma(): Turma
    {
        return Turma::create(['nome'=>'A', 'escola'=>'Escola', 'curso'=>'Curso', 'ano_letivo'=>'2026']);
    }
    private function aluno(Turma $turma, string $hash): EstagioAluno
    {
        return EstagioAluno::create(['nome'=>'Aluno', 'cpf_hash'=>str_repeat($hash, 64), 'nascimento'=>'2007-01-01', 'escola'=>'Escola', 'curso'=>'Curso', 'ano_letivo'=>'2026', 'concedente'=>'', 'orientador'=>'', 'turma_id'=>$turma->id]);
    }
    public function test_altera_todos_inclusive_concluidos_e_registra_historico(): void
    {
        $turma = $this->turma(); $a = $this->aluno($turma, 'a'); $b = $this->aluno($turma, 'b');
        $b->relatorio()->create(['concluido_em'=>now()]);
        $professor = User::factory()->create(['is_professor'=>true]); $professor->turmas()->attach($turma);
        $this->actingAs($professor)->post(route('admin.turmas.carga', $turma), ['carga_horaria'=>240, 'carga_anterior'=>300, 'alunos_confirmados'=>2])->assertSessionHasNoErrors();
        $this->assertEquals(240, $turma->fresh()->carga_horaria);
        $this->assertEquals(240, $a->fresh()->carga_horaria);
        $this->assertEquals(240, $b->fresh()->carga_horaria);
        $this->assertNotNull($b->relatorio->concluido_em);
        $this->assertDatabaseHas('historico_carga_horaria', ['turma_id'=>$turma->id, 'user_id'=>$professor->id, 'carga_anterior'=>300, 'carga_nova'=>240, 'alunos_afetados'=>2]);
    }
    public function test_permissoes_validacao_e_confirmacao_desatualizada(): void
    {
        $turma = $this->turma(); $this->aluno($turma, 'a');
        $professor = User::factory()->create(['is_professor'=>true]);
        $dados = ['carga_horaria'=>240, 'carga_anterior'=>300, 'alunos_confirmados'=>1];
        $this->actingAs($professor)->post(route('admin.turmas.carga', $turma), $dados)->assertForbidden();
        $admin = User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->post(route('admin.turmas.carga', $turma), array_replace($dados, ['alunos_confirmados'=>0]))->assertSessionHasErrors('carga_horaria');
        $this->post(route('admin.turmas.carga', $turma), array_replace($dados, ['carga_horaria'=>0]))->assertSessionHasErrors('carga_horaria');
        $this->assertEquals(300, $turma->fresh()->carga_horaria);
        $this->assertDatabaseCount('historico_carga_horaria', 0);
        $this->post(route('admin.turmas.carga', $turma), $dados)->assertSessionHasNoErrors();
    }
    public function test_novos_alunos_e_importacao_usam_carga_da_turma(): void
    {
        $turma = $this->turma(); $turma->update(['carga_horaria'=>240]);
        $admin = User::factory()->create(['is_admin'=>true]);
        $this->actingAs($admin)->post(route('admin.aluno.salvar'), ['nome'=>'Novo', 'cpf'=>'52998224725', 'nascimento'=>'2007-01-01', 'turma_id'=>$turma->id, 'ativo'=>1, 'carga_horaria'=>999])->assertSessionHasNoErrors();
        $this->assertEquals(240, EstagioAluno::firstOrFail()->carga_horaria);
        $csv = "cpf;nascimento;nome;escola;curso;ano_letivo;inicio;termino;carga_horaria\n52998224725;10/02/2007;Novo;Escola;Curso;2026;;;600\n";
        $this->post(route('admin.importar'), ['turma_id'=>$turma->id, 'arquivo'=>UploadedFile::fake()->createWithContent('alunos.csv', $csv)])->assertSessionHasNoErrors();
        $this->assertEquals(240, EstagioAluno::firstOrFail()->carga_horaria);
    }
}
