<?php

namespace Tests\Feature;

use App\Models\EstagioAluno;
use App\Models\User;
use App\Services\ImportarAlunos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EstagioTest extends TestCase
{
    use RefreshDatabase;

    private function aluno(string $cpf = '52998224725'): EstagioAluno
    {
        return EstagioAluno::create(['nome' => 'Aluno de teste', 'cpf_hash' => hash_hmac('sha256', $cpf, config('app.key')), 'nascimento' => '2007-02-10', 'escola' => 'Escola de teste', 'curso' => 'Informática', 'turma' => '3 A', 'ano_letivo' => '2026', 'concedente' => 'Concedente de teste', 'orientador' => 'Orientador de teste']);
    }

    private function complemento(): array
    {
        return ['serie' => '3º ano', 'empresa_endereco' => 'Rua de teste', 'empresa_numero' => 'S/N',
            'empresa_bairro' => 'Centro', 'empresa_cidade' => 'Cidade de teste - CE',
            'local' => 'Cidade de teste - CE', 'data_relatorio' => '2026-12-22',
            'inicio' => '2026-08-19', 'termino' => '2026-12-22'];
    }

    public function test_additional_identification_is_saved_resumed_and_scoped_to_student(): void
    {
        $aluno = $this->aluno();
        $outro = $this->aluno('11144477735');
        $outro->relatorio()->create(['empresa_endereco' => 'Endereço privado']);
        $this->withSession(['estagio_aluno_id' => $aluno->id])->get('/relatorio')
            ->assertOk()->assertSee('name="empresa_endereco"', false)->assertSee('name="referencias"', false)
            ->assertSee('Rua Melquiades Mourão');
        $this->postJson('/relatorio', [...$this->complemento(),
            'versao' => 0, 'aluno_id' => $outro->id, 'escola_bairro' => 'Bairro corrigido',
            'referencias' => 'Fonte realmente consultada', 'empresa_email' => 'contato@example.org',
        ])->assertOk()->assertJsonPath('versao', 1);
        $this->assertSame('Rua de teste', $aluno->fresh()->relatorio->empresa_endereco);
        $this->assertSame('Endereço privado', $outro->fresh()->relatorio->empresa_endereco);
        $this->get('/relatorio')->assertSee('Fonte realmente consultada')->assertSee('Bairro corrigido');
        $this->postJson('/relatorio', ['versao' => 1, 'empresa_email' => 'invalido'])
            ->assertUnprocessable()->assertJsonPath('errors.empresa_email.0', 'Informe um e-mail válido no campo e-mail da empresa.');
        $this->assertSame('contato@example.org', $aluno->fresh()->relatorio->empresa_email);
        $this->postJson('/relatorio', ['versao' => 1, 'introducao' => 'Salvamento parcial'])->assertOk();
        $this->assertSame('Fonte realmente consultada', $aluno->fresh()->relatorio->referencias);
    }

    public function test_completion_and_student_pdf_require_missing_identification_but_drafts_do_not(): void
    {
        $aluno = $this->aluno();
        $aluno->relatorio()->create(['introducao' => str_repeat('á', 800), 'atividades' => str_repeat('é', 1200), 'conclusao' => str_repeat('í', 600)]);
        $this->withSession(['estagio_aluno_id' => $aluno->id])->postJson('/relatorio', ['versao' => 0])
            ->assertOk()->assertJsonPath('versao', 1);
        $this->postJson('/relatorio', ['versao' => 1, 'concluir' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors(['serie', 'empresa_endereco', 'empresa_cidade', 'inicio', 'termino', 'data_relatorio']);
        $this->getJson('/relatorio/pdf')->assertUnprocessable()->assertJsonValidationErrors('serie');
        $this->assertNull($aluno->fresh()->relatorio->concluido_em);
        $this->postJson('/relatorio', [...$this->complemento(), 'versao' => 1, 'concluir' => 1])
            ->assertOk()->assertJsonPath('concluido', true);
        $this->assertNull($aluno->fresh()->relatorio->referencias);
        $this->assertNull($aluno->fresh()->relatorio->empresa_email);
    }

    public function test_first_open_renders_initial_version_and_allows_saving(): void
    {
        $aluno = $this->aluno();
        $this->withSession(['estagio_aluno_id' => $aluno->id])
            ->get('/relatorio')->assertOk()
            ->assertSee('name="versao" id="versao" value="0"', false);
        $this->postJson('/relatorio', ['versao' => 0, 'introducao' => 'Primeiro texto'])
            ->assertOk()->assertJsonPath('versao', 1);
    }

    public function test_minimums_allow_drafts_and_apply_to_completion_and_pdf(): void
    {
        $aluno = $this->aluno();
        $aluno->relatorio()->create();
        $dados = [...$this->complemento(), 'versao' => 0,
            'introducao' => str_repeat('á', 799).'   ',
            'atividades' => str_repeat('é', 1199).str_repeat(' ', 200),
            'conclusao' => str_repeat('😀', 600)];
        $this->withSession(['estagio_aluno_id' => $aluno->id])->postJson('/relatorio', $dados)->assertOk();
        $this->postJson('/relatorio', ['versao' => 1, 'concluir' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors(['introducao', 'atividades']);
        $this->getJson('/relatorio/pdf')->assertUnprocessable()->assertJsonValidationErrors(['introducao', 'atividades']);
        $this->postJson('/relatorio', ['versao' => 1, 'introducao' => str_repeat('á', 800),
            'atividades' => str_repeat('é', 1200), 'concluir' => 1])->assertOk()->assertJsonPath('concluido', true);
        $this->assertNull($aluno->fresh()->relatorio->referencias);
    }

    public function test_maximums_allow_saving_a_draft_but_block_completion_and_definitive_pdf(): void
    {
        $aluno = $this->aluno();
        $aluno->relatorio()->create();
        $this->withSession(['estagio_aluno_id' => $aluno->id])->postJson('/relatorio', [
            ...$this->complemento(), 'versao' => 0, 'introducao' => str_repeat('á', 2501),
            'atividades' => str_repeat('é', 2201), 'conclusao' => str_repeat('í', 2001),
        ])->assertOk();
        $this->assertSame(str_repeat('é', 2201), $aluno->fresh()->relatorio->atividades);
        $this->postJson('/relatorio', ['versao' => 1, 'concluir' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors(['introducao', 'atividades', 'conclusao']);
        $this->getJson('/relatorio/pdf')->assertUnprocessable()->assertJsonValidationErrors(['introducao', 'atividades', 'conclusao']);
        $this->get('/relatorio/visualizar')->assertOk()->assertSee('Pré-visualização do relatório');
    }

    public function test_student_can_save_own_dates_and_they_are_used_in_pdf(): void
    {
        $aluno = $this->aluno();
        $outro = $this->aluno('11144477735');
        $this->withSession(['estagio_aluno_id' => $aluno->id])->get('/relatorio')
            ->assertOk()->assertSee('name="inicio"', false)->assertSee('name="termino"', false);
        $this->postJson('/relatorio', [
            ...$this->complemento(),
            'versao' => 0, 'inicio' => '2026-08-19', 'termino' => '2026-12-22',
            'introducao' => str_repeat('á', 800), 'atividades' => str_repeat('é', 1200), 'conclusao' => str_repeat('í', 600),
            'aluno_id' => $outro->id,
        ])->assertOk();
        $this->assertSame('2026-08-19', $aluno->fresh()->inicio->format('Y-m-d'));
        $this->assertSame('2026-12-22', $aluno->fresh()->termino->format('Y-m-d'));
        $this->assertNull($outro->fresh()->inicio);
        $this->get('/relatorio')->assertSee('value="2026-08-19"', false)->assertSee('value="2026-12-22"', false);
        $this->get('/relatorio/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_invalid_dates_and_missing_version_return_portuguese_errors_without_saving(): void
    {
        $aluno = $this->aluno();
        $aluno->update(['inicio' => '2026-08-19', 'termino' => '2026-12-22']);
        $aluno->relatorio()->create(['introducao' => 'Texto salvo']);
        $this->withSession(['estagio_aluno_id' => $aluno->id])
            ->postJson('/relatorio', ['versao' => 0, 'inicio' => '2026-12-23', 'introducao' => 'Não salvar'])
            ->assertUnprocessable()->assertJsonPath('errors.termino.0', 'A data de término do estágio deve ser igual ou posterior à data de início.');
        $this->assertSame('2026-08-19', $aluno->fresh()->inicio->format('Y-m-d'));
        $this->assertSame('Texto salvo', $aluno->relatorio->introducao);
        $this->assertSame(0, $aluno->relatorio->versao);
        $this->postJson('/relatorio', ['versao' => 0, 'inicio' => '2026-02-30'])
            ->assertUnprocessable()->assertJsonValidationErrors('inicio');
        $this->postJson('/relatorio', ['introducao' => 'Sem versão'])
            ->assertUnprocessable()->assertJsonPath('errors.versao.0', 'Não foi possível identificar a versão do relatório. Copie seus textos e recarregue a página.');
        $this->postJson('/entrar', [])->assertUnprocessable()
            ->assertJsonPath('errors.cpf.0', 'O campo CPF é obrigatório.');
    }

    public function test_login_and_inactive_student(): void
    {
        $aluno = $this->aluno();
        $this->post('/entrar', ['cpf' => '529.982.247-25', 'nascimento' => '2007-02-10'])->assertRedirect('/relatorio')->assertSessionHas('estagio_aluno_id', $aluno->id);
        $aluno->update(['ativo' => false]);
        $this->get('/relatorio')->assertForbidden();
    }

    public function test_student_can_only_change_own_report_and_conflicts_are_rejected(): void
    {
        $a = $this->aluno();
        $b = $this->aluno('11144477735');
        $a->relatorio()->create();
        $b->relatorio()->create(['introducao' => 'Texto privado']);
        $this->withSession(['estagio_aluno_id' => $a->id])->postJson('/relatorio', ['versao' => 0, 'introducao' => 'Meu texto', 'aluno_id' => $b->id])->assertOk()->assertJsonPath('versao', 1);
        $this->assertSame('Texto privado', $b->relatorio->introducao);
        $this->postJson('/relatorio', ['versao' => 0, 'introducao' => 'Texto antigo'])->assertConflict();
        $this->assertSame('Meu texto', $a->relatorio->introducao);
        $this->get('/admin/alunos/'.$b->id)->assertRedirect('/admin/entrar');
    }

    public function test_completion_validation_pdf_and_resuming(): void
    {
        $a = $this->aluno();
        $a->relatorio()->create();
        $this->withSession(['estagio_aluno_id' => $a->id])->postJson('/relatorio', ['versao' => 0, 'concluir' => 1])->assertUnprocessable();
        $this->postJson('/relatorio', [...$this->complemento(), 'versao' => 0, 'introducao' => str_repeat('á', 800), 'atividades' => str_repeat('é', 1200), 'conclusao' => str_repeat('í', 600), 'concluir' => 1])->assertOk()->assertJsonPath('concluido', true);
        $this->get('/relatorio')->assertOk()->assertSee(str_repeat('á', 800));
        $this->get('/relatorio/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->postJson('/relatorio', ['versao' => 1, 'introducao' => 'Revisado'])->assertOk()->assertJsonPath('concluido', false);
    }

    public function test_professor_authorization_and_views(): void
    {
        $a = $this->aluno();
        $a->relatorio()->create(['introducao' => 'Texto do estudante']);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin')->assertForbidden();
        $user->is_admin = true;
        $user->save();
        $this->get('/admin')->assertOk()->assertSee($a->nome);
        $this->get('/admin/alunos/'.$a->id)->assertOk()->assertSee('Texto do estudante');
        $this->getJson('/admin/alunos/'.$a->id.'/pdf')->assertUnprocessable();
    }

    public function test_professor_login_only_accepts_admin_accounts(): void
    {
        $user = User::factory()->create(['password' => 'A-long-password123']);
        $this->post('/admin/entrar', ['email' => $user->email, 'password' => 'A-long-password123'])->assertSessionHasErrors();
        $this->assertGuest();
        $user->is_admin = true;
        $user->save();
        $this->post('/admin/entrar', ['email' => $user->email, 'password' => 'A-long-password123'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
    }

    public function test_unauthenticated_writes_and_pdf_are_denied(): void
    {
        $this->postJson('/relatorio', ['versao' => 0])->assertForbidden();
        $this->get('/relatorio/pdf')->assertForbidden();
    }

    public function test_account_rate_limit(): void
    {
        $a = $this->aluno();
        RateLimiter::hit('aluno:'.$a->cpf_hash, 300);
        for ($i = 0; $i < 4; $i++) {
            RateLimiter::hit('aluno:'.$a->cpf_hash, 300);
        }
        $this->post('/entrar', ['cpf' => '52998224725', 'nascimento' => '2007-02-10'])->assertSessionHasErrors('cpf');
        $this->assertSessionMissingStudent();
    }

    private function assertSessionMissingStudent(): void
    {
        $this->assertNull(session('estagio_aluno_id'));
    }

    public function test_import_is_atomic_and_preserves_reports(): void
    {
        $a = $this->aluno();
        $a->relatorio()->create(['introducao' => 'Preservar']);
        $path = tempnam(sys_get_temp_dir(), 'csv');
        $header = "cpf;nascimento;nome;escola;curso;turma;ano_letivo;concedente;inicio;termino;carga_horaria;orientador\n";
        $valid = "52998224725;10/02/2007;Nome atualizado;Escola;Curso;3 B;2026;Empresa;01/02/2026;30/06/2026;300;Professor\n";
        try {
            file_put_contents($path, $header.$valid);
            $this->assertSame(1, app(ImportarAlunos::class)->importar($path));
            $this->assertSame('Preservar', $a->fresh()->relatorio->introducao);
            file_put_contents($path, $header.str_replace('Nome atualizado', 'Não persistir', $valid)."11144477735;31/02/2007;Outro;Escola;Curso;3 B;2026;Empresa;;;300;Professor\n");
            try {
                app(ImportarAlunos::class)->importar($path);
                $this->fail('Importação deveria falhar.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Linha 3', $e->getMessage());
            }
            $this->assertSame('Nome atualizado', $a->fresh()->nome);
            $this->assertSame(1, EstagioAluno::count());
        } finally {
            unlink($path);
        }
    }
}
