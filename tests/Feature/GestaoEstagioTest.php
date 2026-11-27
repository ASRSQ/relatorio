<?php

namespace Tests\Feature;

use App\Models\{Concedente, EstagioAluno, SolicitacaoConcedente, Turma, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestaoEstagioTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_deletion_preserves_linked_students_and_requests(): void
    {
        $turma = Turma::create(['nome' => 'A', 'curso' => 'Informática', 'ano_letivo' => '2026']);
        $aluno = $this->aluno($turma, str_repeat('a', 64));
        $professor = User::factory()->create(['is_professor' => true]);
        $professor->turmas()->attach($turma);
        $livre = Concedente::create(['nome' => 'Livre']);
        $vinculada = Concedente::create(['nome' => 'Vinculada']);
        $solicitada = Concedente::create(['nome' => 'Solicitada']);
        $aluno->update(['concedente_id' => $vinculada->id]);
        SolicitacaoConcedente::create(['aluno_id' => $aluno->id, 'concedente_id' => $solicitada->id, 'situacao' => 'pendente']);

        $this->actingAs($professor)->delete('/admin/concedentes/'.$livre->id)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('concedentes', ['id' => $livre->id]);
        foreach ([$vinculada, $solicitada] as $empresa) {
            $this->delete('/admin/concedentes/'.$empresa->id)->assertSessionHasErrors('concedente');
            $this->assertDatabaseHas('concedentes', ['id' => $empresa->id]);
        }
        $this->assertSame($vinculada->id, $aluno->fresh()->concedente_id);
        $pessoal = Concedente::create(['nome' => 'Pessoal', 'aluno_proprietario_id' => $aluno->id]);
        $this->delete('/admin/concedentes/'.$pessoal->id)->assertNotFound();
        $this->assertDatabaseHas('concedentes', ['id' => $pessoal->id]);
    }

    public function test_teacher_can_assign_an_active_general_company_from_dashboard(): void
    {
        $turma = Turma::create(['nome' => 'A', 'curso' => 'Informática', 'ano_letivo' => '2026']);
        $aluno = $this->aluno($turma, str_repeat('a', 64));
        $professor = User::factory()->create(['is_professor' => true]);
        $professor->turmas()->attach($turma);
        $empresa = Concedente::create(['nome' => 'Empresa disponível', 'ativa' => true]);
        $inativa = Concedente::create(['nome' => 'Empresa inativa', 'ativa' => false]);

        $this->actingAs($professor)->get('/admin')->assertOk()
            ->assertSee('Selecionar concedente para '.$aluno->nome)->assertSee('Empresa disponível')->assertDontSee('Empresa inativa');
        $this->from('/admin?busca=Aluno')->post('/admin/alunos/'.$aluno->id.'/concedente', ['concedente_id' => $empresa->id])
            ->assertRedirect('/admin?busca=Aluno')->assertSessionHasNoErrors();
        $this->assertSame($empresa->id, $aluno->fresh()->concedente_id);
        $this->post('/admin/alunos/'.$aluno->id.'/concedente', ['concedente_id' => $inativa->id])->assertSessionHasErrors('concedente_id');
    }

    public function test_validation_messages_explain_company_and_import_errors(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/concedentes', ['nome' => 'Empresa', 'estado' => 'C', 'ativa' => 1])
            ->assertSessionHasErrors(['estado' => 'Informe a UF com duas letras, como CE.']);
        $this->post('/admin/cadastro-aluno', ['nome' => 'Aluno', 'cpf' => '123', 'nascimento' => '2008-01-01', 'turma_id' => 999, 'ativo' => 1])
            ->assertSessionHasErrors(['turma_id' => 'O valor selecionado para turma não existe ou não está disponível. Atualize a página e escolha novamente.']);
    }

    public function test_company_fields_and_document_validation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->post('/admin/concedentes', [
            'nome' => 'Empresa completa', 'nome_fantasia' => 'Fantasia', 'documento' => '11.222.333/0001-81',
            'endereco' => 'Rua Um', 'numero' => '123', 'bairro' => 'Centro', 'cidade' => 'Fortaleza',
            'estado' => 'CE', 'telefone' => '(85) 99999-9999', 'email' => 'empresa@example.org',
            'responsavel' => 'Responsável', 'ativa' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('concedentes', ['nome' => 'Empresa completa', 'documento' => '11222333000181', 'cidade' => 'Fortaleza']);

        $this->post('/admin/concedentes', ['nome' => 'Outra empresa', 'documento' => '123', 'ativa' => 1])
            ->assertSessionHasErrors(['documento' => 'Informe um CPF com 11 dígitos ou um CNPJ com 14 dígitos.']);
    }

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
