<?php

namespace App\Http\Controllers;

use App\Models\{Concedente, EstagioAluno, SolicitacaoConcedente, Turma, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GestaoController extends Controller
{
    private function admin(): void { abort_unless(Auth::user()->is_admin, 403); }
    private function alunoProprio(Request $request): EstagioAluno
    {
        $aluno = EstagioAluno::find($request->session()->get('estagio_aluno_id'));
        abort_unless($aluno && $aluno->ativo, 403);
        return $aluno;
    }
    private function dadosEmpresa(Request $request, ?Concedente $empresa = null): array
    {
        $request->merge(['documento' => preg_replace('/\D/', '', $request->input('documento', '')) ?: null]);
        $dados = $request->validate([
            'nome' => 'required|string|max:255', 'nome_fantasia' => 'nullable|string|max:255',
            'documento' => ['nullable', 'regex:/^(\d{11}|\d{14})$/', Rule::unique('concedentes', 'documento')->ignore($empresa?->id)],
            'endereco' => 'nullable|string|max:255', 'numero' => 'nullable|string|max:30', 'bairro' => 'nullable|string|max:255',
            'cidade' => 'nullable|string|max:255', 'estado' => 'nullable|alpha|size:2', 'telefone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255', 'responsavel' => 'nullable|string|max:255',
        ]);
        $documento = $dados['documento'] ?? null;
        if ($documento && ! $this->documentoValido($documento)) throw ValidationException::withMessages(['documento'=>'Informe um CPF ou CNPJ válido.']);
        if (! $empresa && empty($dados['documento']) && ! $request->boolean('confirmar_semelhante')) {
            $semelhante = Concedente::whereRaw('LOWER(nome) = ?', [mb_strtolower($dados['nome'])])
                ->when(! empty($dados['cidade']), fn ($q) => $q->whereRaw('LOWER(cidade) = ?', [mb_strtolower($dados['cidade'])]))->exists();
            if ($semelhante) throw ValidationException::withMessages(['nome'=>'Existe uma concedente com identificação semelhante. Pesquise antes de cadastrar; se for outra empresa, marque a confirmação.']);
        }
        return $dados;
    }
    private function documentoValido(string $documento): bool
    {
        if (strlen($documento) === 11) return EstagioController::cpfValido($documento);
        if (strlen($documento) !== 14 || preg_match('/^(\d)\1+$/', $documento)) return false;
        foreach ([12 => [5,4,3,2,9,8,7,6,5,4,3,2], 13 => [6,5,4,3,2,9,8,7,6,5,4,3,2]] as $pos => $pesos) {
            $soma = 0;
            foreach ($pesos as $i => $peso) $soma += (int) $documento[$i] * $peso;
            $resto = $soma % 11;
            if ((int) $documento[$pos] !== ($resto < 2 ? 0 : 11 - $resto)) return false;
        }
        return true;
    }
    public function professores()
    {
        $this->admin(); return view('gestao.professores', ['professores' => User::where('is_professor', true)->with('turmas')->orderBy('name')->get(), 'turmas' => Turma::orderBy('nome')->get()]);
    }
    public function salvarProfessor(Request $request, ?User $professor = null)
    {
        $this->admin(); if ($professor) abort_unless($professor->is_professor && ! $professor->is_admin, 404);
        $dados = $request->validate(['name' => 'required|string|max:255', 'email' => ['required','email','max:255',Rule::unique('users')->ignore($professor?->id)], 'password' => ($professor ? 'nullable' : 'required').'|string|min:8|max:255', 'ativo' => 'required|boolean', 'turmas' => 'array', 'turmas.*' => 'integer|exists:turmas,id']);
        DB::transaction(function () use ($dados, $professor) {
            $professor ??= new User(); $professor->name = $dados['name']; $professor->email = $dados['email'];
            if (! empty($dados['password'])) $professor->password = $dados['password'];
            $professor->ativo = $dados['ativo']; $professor->is_professor = true; $professor->save(); $professor->turmas()->sync($dados['turmas'] ?? []);
        });
        return back()->with('ok', 'Professor e vínculos salvos.');
    }
    public function turmas()
    {
        $usuario = Auth::user(); $turmas = $usuario->is_admin ? Turma::with('professores')->withCount('alunos')->get() : $usuario->turmas()->with('professores')->withCount('alunos')->get();
        $professores = $usuario->is_admin ? User::where('is_professor', true)->where('ativo', true)->orderBy('name')->get() : collect();
        return view('gestao.turmas', compact('turmas', 'professores'));
    }
    public function salvarTurma(Request $request, ?Turma $turma = null)
    {
        $this->admin();
        $dados = $request->validate(['nome'=>'required|string|max:100','escola'=>'nullable|string|max:255','curso'=>'required|string|max:255','serie'=>'nullable|string|max:255','ano_letivo'=>'required|string|max:10','turno'=>'nullable|string|max:255','ativa'=>'required|boolean']);
        $dados['identidade_hash'] = ! empty($dados['escola']) && ! empty($dados['serie']) ? Turma::chaveIdentidade($dados) : null;
        if ($dados['identidade_hash'] && Turma::where('identidade_hash', $dados['identidade_hash'])->when($turma, fn ($q) => $q->where('id', '!=', $turma->id))->exists()) throw ValidationException::withMessages(['nome'=>'Já existe uma turma com esta identificação.']);
        ($turma ?? new Turma())->fill($dados)->save(); return back()->with('ok', 'Turma salva.');
    }
    public function vincularProfessor(Request $request, Turma $turma)
    {
        $this->admin();
        $dados = $request->validate(['professor_id' => ['required', Rule::exists('users', 'id')->where('is_professor', true)->where('ativo', true)]]);
        $turma->professores()->syncWithoutDetaching([$dados['professor_id']]);
        return back()->with('ok', 'Professor vinculado à turma.');
    }
    public function empresas(Request $request)
    {
        $busca = $request->validate(['busca'=>'nullable|string|max:255'])['busca'] ?? '';
        $usuario = Auth::user();
        $empresas = Concedente::with(['alunos' => fn ($q) => $q->when(! $usuario->is_admin, fn ($q) => $q->whereIn('turma_id', $usuario->turmas()->select('turmas.id')))->with('turmaVinculada')])->when($busca, fn ($q) => $q->where(fn ($q) => $q->where('nome','like',"%{$busca}%")->orWhere('documento','like',"%{$busca}%")))->orderBy('nome')->get();
        return view('gestao.empresas', compact('empresas'));
    }
    public function salvarEmpresa(Request $request, ?Concedente $empresa = null)
    {
        $dados = $this->dadosEmpresa($request, $empresa); $dados['ativa'] = $request->boolean('ativa');
        ($empresa ?? new Concedente())->fill($dados)->save(); return back()->with('ok', 'Concedente salva.');
    }
    public function vincular(Request $request, EstagioAluno $aluno)
    {
        abort_unless(Auth::user()->podeVerAluno($aluno), 403);
        $dados = $request->validate(['concedente_id'=>['required',Rule::exists('concedentes','id')->where('ativa', true)]]);
        $aluno->update($dados); return back()->with('ok', 'Concedente vinculada ao aluno.');
    }
    public function alunoForm(?EstagioAluno $aluno = null)
    {
        if ($aluno) abort_unless(Auth::user()->podeVerAluno($aluno), 403);
        else $this->admin();
        $turmas = Auth::user()->is_admin ? Turma::orderBy('nome')->get() : Auth::user()->turmas()->orderBy('nome')->get();
        return view('gestao.aluno', compact('aluno', 'turmas'));
    }
    public function salvarAluno(Request $request, ?EstagioAluno $aluno = null)
    {
        if ($aluno) abort_unless(Auth::user()->podeVerAluno($aluno), 403);
        else $this->admin();
        $dados = $request->validate(['nome'=>'required|string|max:255','cpf'=>($aluno ? 'nullable' : 'required').'|string|max:20','nascimento'=>'required|date|before:today','matricula'=>'nullable|string|max:255','turma_id'=>'required|integer|exists:turmas,id','ativo'=>'required|boolean']);
        if (! Auth::user()->is_admin) abort_unless(Auth::user()->turmas()->where('turmas.id', $dados['turma_id'])->exists(), 403);
        $turma = Turma::findOrFail($dados['turma_id']);
        if (! empty($dados['cpf'])) {
            $cpf = preg_replace('/\D/', '', $dados['cpf']);
            if (! EstagioController::cpfValido($cpf)) throw ValidationException::withMessages(['cpf'=>'Informe um CPF válido.']);
            $hash = hash_hmac('sha256', $cpf, config('app.key'));
            if (EstagioAluno::where('cpf_hash', $hash)->when($aluno, fn ($q) => $q->where('id', '!=', $aluno->id))->exists()) throw ValidationException::withMessages(['cpf'=>'Já existe um aluno com este CPF.']);
            $dados['cpf_hash'] = $hash;
        }
        unset($dados['cpf']);
        $dados += ['turma'=>$turma->nome, 'curso'=>$turma->curso, 'ano_letivo'=>$turma->ano_letivo];
        if (! $aluno) $dados += ['escola'=>config('estagio.escola.nome', 'EEEP Monsenhor Luis Ximenes Freire'), 'concedente'=>'', 'orientador'=>''];
        ($aluno ?? new EstagioAluno())->fill($dados)->save();
        return redirect()->route('admin.index')->with('ok', 'Aluno salvo.');
    }
    public function minhaEmpresa(Request $request)
    {
        $aluno = $this->alunoProprio($request); $busca = $request->validate(['busca'=>'nullable|string|max:255'])['busca'] ?? '';
        $empresas = Concedente::where('ativa', true)->when($busca, fn ($q) => $q->where(fn ($q) => $q->where('nome','like',"%{$busca}%")->orWhere('documento','like',"%{$busca}%")))->orderBy('nome')->limit(100)->get();
        return view('estagio-minha-concedente', ['aluno'=>$aluno, 'empresas'=>$empresas, 'solicitacoes'=>$aluno->solicitacoes()->latest()->get()]);
    }
    public function solicitar(Request $request)
    {
        $aluno = $this->alunoProprio($request);
        if ($request->filled('alterar_id')) {
            $empresa = Concedente::whereKey($request->integer('alterar_id'))->whereKey($aluno->concedente_id)->where('ativa', true)->firstOrFail();
            $dados = $this->dadosEmpresa($request, $empresa);
            $solicitacao = ['concedente_id' => $empresa->id, 'dados' => $dados];
        } elseif ($request->filled('concedente_id')) {
            $solicitacao = $request->validate(['concedente_id'=>['required',Rule::exists('concedentes','id')->where('ativa', true)]]);
        } else {
            $dados = $this->dadosEmpresa($request);
            $solicitacao = ['dados'=>$dados];
        }
        DB::transaction(function () use ($aluno, $solicitacao) {
            $aluno = EstagioAluno::whereKey($aluno->id)->lockForUpdate()->firstOrFail();
            if ($aluno->solicitacoes()->where('situacao', 'pendente')->exists()) throw ValidationException::withMessages(['solicitacao'=>'Você já possui uma solicitação aguardando análise.']);
            $aluno->solicitacoes()->create($solicitacao);
        });
        return back()->with('ok', 'Solicitação enviada para análise do professor.');
    }
    public function pendencias()
    {
        $usuario = Auth::user();
        $solicitacoes = SolicitacaoConcedente::with(['aluno.turmaVinculada','concedente'])->where('situacao','pendente')->whereHas('aluno', fn ($q) => $q->when(! $usuario->is_admin, fn ($q) => $q->whereIn('turma_id', $usuario->turmas()->select('turmas.id'))))->latest()->get();
        return view('gestao.pendencias', compact('solicitacoes'));
    }
    public function decidir(Request $request, SolicitacaoConcedente $solicitacao)
    {
        abort_unless(Auth::user()->podeVerAluno($solicitacao->aluno), 403);
        $dados = $request->validate(['decisao'=>'required|in:aprovada,rejeitada,correcao', 'justificativa'=>'required_unless:decisao,aprovada|nullable|string|max:2000']);
        DB::transaction(function () use ($solicitacao, $dados) {
            $solicitacao = SolicitacaoConcedente::whereKey($solicitacao->id)->lockForUpdate()->firstOrFail();
            abort_unless(Auth::user()->podeVerAluno($solicitacao->aluno), 403);
            if ($solicitacao->situacao !== 'pendente') throw ValidationException::withMessages(['solicitacao'=>'Esta solicitação já foi analisada.']);
            if ($dados['decisao'] === 'aprovada') {
                if ($solicitacao->concedente_id) {
                    $empresa = Concedente::whereKey($solicitacao->concedente_id)->where('ativa',true)->lockForUpdate()->first();
                    if (! $empresa) throw ValidationException::withMessages(['concedente'=>'A concedente não está mais ativa.']);
                    if ($solicitacao->dados) {
                        if ($solicitacao->aluno->concedente_id !== $empresa->id) throw ValidationException::withMessages(['concedente'=>'O vínculo foi alterado. Solicite a alteração novamente.']);
                        $doc = $solicitacao->dados['documento'] ?? null;
                        if ($doc && Concedente::where('documento', $doc)->where('id', '!=', $empresa->id)->exists()) throw ValidationException::withMessages(['documento'=>'Já existe outra concedente com este CPF/CNPJ.']);
                        $empresa->fill($solicitacao->dados)->save();
                    }
                } else {
                    $doc = $solicitacao->dados['documento'] ?? null;
                    if ($doc && Concedente::where('documento',$doc)->exists()) throw ValidationException::withMessages(['documento'=>'Já existe uma concedente com este CPF/CNPJ. Solicite vínculo com o cadastro existente.']);
                    $empresa = Concedente::create($solicitacao->dados);
                }
                $solicitacao->aluno()->update(['concedente_id'=>$empresa->id]); $solicitacao->concedente_id = $empresa->id;
            }
            $solicitacao->situacao = $dados['decisao']; $solicitacao->justificativa = $dados['justificativa'] ?? null;
            $solicitacao->decidido_por = Auth::id(); $solicitacao->decidido_em = now(); $solicitacao->save();
        });
        return back()->with('ok', 'Solicitação analisada.');
    }
}
