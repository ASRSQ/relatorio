<?php
namespace Tests\Feature;

use App\Models\{Turma, User, EstagioAluno};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportacaoTurmasTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('alunos.csv', "cpf;nascimento;nome;escola;curso;ano_letivo;inicio;termino;carga_horaria;orientador\n".$rows);
    }
    private function row(string $cpf = '52998224725'): string
    {
        return "$cpf;10/02/2007;Aluno;Escola;Curso;2026;;;300;Professor\n";
    }
    public function test_painel_exibe_textos_em_utf8_e_cabecalho_csv(): void
    {
        $admin = User::factory()->create(['is_admin'=>true, 'ativo'=>true]);
        $this->actingAs($admin)->get(route('admin.index'))
            ->assertOk()->assertSee('Identificação da turma')->assertSee('Série')
            ->assertSee('ponto e vírgula')
            ->assertSee('cpf;nascimento;nome;escola;curso;ano_letivo;concedente;inicio;termino;carga_horaria;orientador');
        $turma = Turma::create(['nome'=>'A','curso'=>'Curso','ano_letivo'=>'2026']);
        $this->get(route('admin.turmas'))->assertOk()->assertSee('Não informada')->assertSee('Situação');
    }
    public function test_importacao_cria_reutiliza_e_vincula_professor(): void
    {
        $professor = User::factory()->create(['is_professor'=>true, 'ativo'=>true]);
        $dados = ['nome'=>'3 A', 'escola'=>'Escola', 'curso'=>'Curso', 'serie'=>'3', 'ano_letivo'=>'2026'];
        $this->actingAs($professor)->post(route('admin.importar'), $dados + ['arquivo'=>$this->csv($this->row())])->assertSessionHasNoErrors()->assertSessionHas('importacao.importados', 1);
        $turma = Turma::firstOrFail();
        $this->assertTrue($turma->professores->contains($professor));
        $this->assertSame($turma->id, EstagioAluno::firstOrFail()->turma_id);
        $this->actingAs($professor)->post(route('admin.importar'), $dados + ['arquivo'=>$this->csv($this->row().$this->row())])->assertSessionHasNoErrors()->assertSessionHas('importacao.atualizados', 1)->assertSessionHas('importacao.ignorados', 1);
        $this->assertSame(1, Turma::count());
    }
    public function test_erro_reverte_turma_e_alunos(): void
    {
        $admin = User::factory()->create(['is_admin'=>true, 'ativo'=>true]);
        $dados = ['nome'=>'3 A', 'escola'=>'Escola', 'curso'=>'Curso', 'serie'=>'3', 'ano_letivo'=>'2026'];
        $this->actingAs($admin)->post(route('admin.importar'), $dados + ['arquivo'=>$this->csv($this->row().$this->row('11111111111'))])->assertSessionHasErrors('arquivo');
        $this->assertSame(0, Turma::count());
        $this->assertSame(0, EstagioAluno::count());
    }
    public function test_escola_normalizada_sem_alterar_nomes_originais(): void
    {
        $admin = User::factory()->create(['is_admin'=>true, 'ativo'=>true]);
        $turma = Turma::create(['nome'=>'A', 'escola'=>'EEEP Monsenhor Luís Ximenes Freire', 'curso'=>'Curso', 'ano_letivo'=>'2026']);
        $linha = str_replace('Escola', ' EEEP   MONSENHOR LUIS XIMENES FREIRE ', $this->row());
        $this->actingAs($admin)->post(route('admin.importar'), ['turma_id'=>$turma->id, 'arquivo'=>$this->csv($linha)])->assertSessionHasNoErrors();
        $aluno = EstagioAluno::firstOrFail();
        $this->assertSame('EEEP   MONSENHOR LUIS XIMENES FREIRE', $aluno->escola);
        $linha = str_replace('Escola', 'EEEP Monsenhor Luís, Ximenes Freire', $this->row());
        $this->post(route('admin.importar'), ['turma_id'=>$turma->id, 'arquivo'=>$this->csv($linha)])->assertSessionHasNoErrors();
        $this->assertSame('EEEP   MONSENHOR LUIS XIMENES FREIRE', $aluno->fresh()->escola);
        $this->assertSame('EEEP Monsenhor Luís Ximenes Freire', $turma->fresh()->escola);
    }

    public function test_escola_diferente_informa_dados_e_reverte_alteracoes(): void
    {
        $admin = User::factory()->create(['is_admin'=>true, 'ativo'=>true]);
        $turma = Turma::create(['nome'=>'A', 'escola'=>'Escola Central', 'curso'=>'Curso', 'ano_letivo'=>'2026']);
        $primeira = str_replace('Escola', 'Escola Central', $this->row());
        $segunda = str_replace('Escola', 'Escola Central Norte', $this->row('11144477735'));
        $resposta = $this->actingAs($admin)->post(route('admin.importar'), ['turma_id'=>$turma->id, 'arquivo'=>$this->csv($primeira.$segunda)]);
        $resposta->assertSessionHasErrors('arquivo');
        $mensagem = session('errors')->first('arquivo');
        $this->assertStringContainsString('Linha 3', $mensagem);
        $this->assertStringContainsString('Escola cadastrada na turma: Escola Central', $mensagem);
        $this->assertStringContainsString('Escola identificada no CSV: Escola Central Norte', $mensagem);
        $this->assertSame(0, EstagioAluno::count());
    }

    public function test_turma_sem_escola_nao_aceita_importacao(): void
    {
        $admin = User::factory()->create(['is_admin'=>true, 'ativo'=>true]);
        $turma = Turma::create(['nome'=>'A', 'curso'=>'Curso', 'ano_letivo'=>'2026']);
        $this->actingAs($admin)->post(route('admin.importar'), ['turma_id'=>$turma->id, 'arquivo'=>$this->csv($this->row())])->assertSessionHasErrors('arquivo');
        $this->assertSame(0, EstagioAluno::count());
    }

    public function test_professor_nao_pode_importar_para_turma_de_outro(): void
    {
        $professor = User::factory()->create(['is_professor'=>true, 'ativo'=>true]);
        $turma = Turma::create(['nome'=>'3 A', 'curso'=>'Curso', 'ano_letivo'=>'2026']);
        $this->actingAs($professor)->post(route('admin.importar'), ['turma_id'=>$turma->id, 'arquivo'=>$this->csv($this->row())])->assertForbidden();
    }
}
