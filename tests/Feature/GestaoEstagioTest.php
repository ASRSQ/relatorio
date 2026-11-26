<?php

namespace Tests\Feature;

use App\Models\{Concedente, EstagioAluno, SolicitacaoConcedente, Turma, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestaoEstagioTest extends TestCase
{
    use RefreshDatabase;

    private function aluno(Turma $turma, string $hash): EstagioAluno
    {
        return EstagioAluno::create(['nome'=>'Aluno de teste', 'cpf_hash'=>$hash, 'nascimento'=>'2008-01-01', 'escola'=>'Escola', 'curso'=>'Informática', 'ano_letivo'=>'2026', 'concedente'=>'Empresa legada', 'orientador'=>'Professor', 'turma_id'=>$turma->id]);
    }

    public function test_professor_only_accesses_assigned_students_and_cannot_manage_teachers(): void
    {
        $a = Turma::create(['nome'=>'A','curso'=>'Informática','ano_letivo'=>'2026']);
        $b = Turma::create(['nome'=>'B','curso'=>'Informática','ano_letivo'=>'2026']);
        $proprio = $this->aluno($a, str_repeat('a',64)); $outro = $this->aluno($b, str_repeat('b',64));
        $professor = User::factory()->create(['is_professor'=>true]); $professor->turmas()->attach($a);
        $this->actingAs($professor)->get('/admin/alunos/'.$proprio->id)->assertOk();
        $this->get('/admin/alunos/'.$outro->id)->assertForbidden();
        $this->get('/admin/professores')->assertForbidden();
        $this->post('/admin/alunos/'.$outro->id.'/concedente', [])->assertForbidden();
    }

    public function test_student_copies_and_edits_private_company_without_changing_general_record(): void
    {
        $turma = Turma::create(['nome'=>'A','curso'=>'Informática','ano_letivo'=>'2026']);
        $aluno = $this->aluno($turma, str_repeat('a',64));
        $outro = $this->aluno($turma, str_repeat('b',64));
        $geral = Concedente::create(['nome'=>'Empresa geral', 'documento'=>'11222333000181']);
        $this->withSession(['estagio_aluno_id'=>$aluno->id])->post('/minha-concedente', ['concedente_id'=>$geral->id])->assertRedirect();
        $copia = $aluno->fresh()->empresa;
        $this->assertNotSame($geral->id, $copia->id);
        $this->assertSame($aluno->id, $copia->aluno_proprietario_id);
        $this->assertSame($geral->documento, $copia->documento);
        $this->post('/minha-concedente', ['nome'=>'Minha empresa', 'documento'=>'11222333000181'])->assertRedirect();
        $this->assertSame('Minha empresa', $copia->fresh()->nome);
        $this->assertSame('Empresa geral', $geral->fresh()->nome);
        $this->withSession(['estagio_aluno_id'=>$outro->id])->postJson('/minha-concedente', ['concedente_id'=>$copia->id])->assertUnprocessable();
        $this->assertDatabaseCount('solicitacoes_concedente', 0);
    }

    public function test_finalized_snapshot_is_not_changed_by_shared_company_update(): void
    {
        $turma = Turma::create(['nome'=>'A','curso'=>'Informática','ano_letivo'=>'2026']);
        $aluno = $this->aluno($turma, str_repeat('a',64));
        $empresa = Concedente::create(['nome'=>'Nome original','cidade'=>'Cidade','estado'=>'CE']);
        $aluno->update(['concedente_id'=>$empresa->id]);
        $relatorio = $aluno->relatorio()->create();
        $relatorio->aplicarConcedente($aluno->fresh(), true);
        $relatorio->concluido_em = now(); $relatorio->save();
        $empresa->update(['nome'=>'Nome atualizado']);
        $aluno = $aluno->fresh(); $relatorio = $relatorio->fresh(); $relatorio->aplicarConcedente($aluno);
        $this->assertSame('Nome original', $aluno->concedente);
        $this->assertSame('Cidade - CE', $relatorio->empresa_cidade);
        $this->assertSame('Empresa legada', $aluno->fresh()->concedente);
    }

    public function test_management_views_and_pending_company_creation(): void
    {
        $turma = Turma::create(['nome'=>'A','curso'=>'Informática','ano_letivo'=>'2026']);
        $aluno = $this->aluno($turma, str_repeat('a',64));
        $admin = User::factory()->create(['is_admin'=>true]);
        foreach (['/admin','/admin/professores','/admin/turmas','/admin/concedentes','/admin/pendencias','/admin/cadastro-aluno'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->post('/admin/professores', ['name'=>'Professor','email'=>'professor@example.org','password'=>'senha-segura','ativo'=>1,'turmas'=>[$turma->id]])->assertRedirect();
        $professor = User::where('email','professor@example.org')->firstOrFail();
        $this->assertTrue($professor->is_professor);
        $this->assertTrue($professor->turmas()->whereKey($turma->id)->exists());
        $this->post('/admin/turmas', ['nome'=>'B','curso'=>'Informática','ano_letivo'=>'2026','ativa'=>1])->assertRedirect();
        $this->assertDatabaseHas('turmas', ['nome'=>'B']);
        $this->post('/admin/concedentes', ['nome'=>'Empresa','documento'=>'11.222.333/0001-81','ativa'=>1])->assertRedirect();
        $this->assertDatabaseHas('concedentes', ['documento'=>'11222333000181']);
        auth()->logout();
        $this->withSession(['estagio_aluno_id'=>$aluno->id])->get('/minha-concedente')->assertOk();
        $this->post('/minha-concedente', ['nome'=>'Outra empresa','cidade'=>'Cidade'])->assertRedirect();
        $empresa = Concedente::where('nome', 'Outra empresa')->firstOrFail();
        $this->assertSame($aluno->id, $empresa->aluno_proprietario_id);
        $this->assertSame($empresa->id, $aluno->fresh()->concedente_id);
        $this->assertDatabaseCount('solicitacoes_concedente', 0);
    }
}
