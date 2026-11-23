<?php

namespace App\Http\Controllers;

use App\Models\EstagioAluno;
use App\Services\ImportarAlunos;
use App\Services\RelatorioPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function loginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:255']);
        $key = 'professor:'.hash('sha256', mb_strtolower($data['email']));
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Aguarde alguns minutos antes de tentar novamente.']);
        }
        RateLimiter::hit($key, 300);
        if (! Auth::attempt([...$data, 'ativo' => true]) || (! Auth::user()->is_admin && ! Auth::user()->is_professor)) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->forget('estagio_aluno_id');

        return redirect()->route('admin.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    public function index(Request $request)
    {
        $data = $request->validate(['busca' => 'nullable|string|max:100', 'status' => 'nullable|in:nao_iniciado,iniciado,preenchendo,concluido']);
        $usuario = Auth::user();
        $base = EstagioAluno::query()->when(! $usuario->is_admin, fn ($q) => $q->whereIn('turma_id', $usuario->turmas()->select('turmas.id')));
        $query = (clone $base)->with('relatorio')->orderBy('nome');
        if ($request->filled('turma_id')) $query->where('turma_id', $request->integer('turma_id'));
        if ($search = $data['busca'] ?? null) {
            $query->where(fn ($q) => $q->where('nome', 'like', "%$search%")->orWhere('turma', 'like', "%$search%"));
        }
        if ($status = $data['status'] ?? null) {
            if ($status === 'nao_iniciado') {
                $query->whereDoesntHave('relatorio');
            } else {
                $query->whereHas('relatorio', function ($q) use ($status) {
                    if ($status === 'concluido') {
                        $q->whereNotNull('concluido_em');
                    } else {
                        $q->whereNull('concluido_em');
                        $status === 'iniciado' ? $q->where('versao', 0) : $q->where('versao', '>', 0);
                    }
                });
            }
        }
        $alunos = $query->paginate(20)->withQueryString();
        $totais = [
            'Alunos' => (clone $base)->count(),
            'Não iniciados' => (clone $base)->whereDoesntHave('relatorio')->count(),
            'Em preenchimento' => (clone $base)->whereHas('relatorio', fn ($q) => $q->whereNull('concluido_em'))->count(),
            'Finalizados' => (clone $base)->whereHas('relatorio', fn ($q) => $q->whereNotNull('concluido_em'))->count(),
            'Concedentes cadastradas' => \App\Models\Concedente::whereNull('aluno_proprietario_id')->count(),
            'Pendências' => \App\Models\SolicitacaoConcedente::where('situacao', 'pendente')->whereHas('aluno', fn ($q) => $q->when(! $usuario->is_admin, fn ($q) => $q->whereIn('turma_id', $usuario->turmas()->select('turmas.id'))))->count(),
        ];
        $turmas = $usuario->is_admin ? \App\Models\Turma::orderBy('nome')->get() : $usuario->turmas()->orderBy('nome')->get();

        return view('admin.index', compact('alunos', 'totais', 'turmas'));
    }

    public function importar(Request $request, ImportarAlunos $importador)
    {
        $dados = $request->validate([
            'arquivo' => 'required|file|max:5120|mimes:csv,txt', 'turma_id' => 'nullable|integer|exists:turmas,id',
            'nome' => 'required_without:turma_id|nullable|string|max:100', 'escola' => 'required_without:turma_id|nullable|string|max:255',
            'curso' => 'required_without:turma_id|nullable|string|max:255', 'serie' => 'required_without:turma_id|nullable|string|max:255',
            'ano_letivo' => 'required_without:turma_id|nullable|string|max:10',
        ]);
        $resultado = $importador->importar($request->file('arquivo')->getRealPath(), $dados, Auth::user());
        return back()->with('importacao', $resultado);
    }

    public function show(EstagioAluno $aluno)
    {
        abort_unless(Auth::user()->podeVerAluno($aluno), 403);
        $relatorio = $aluno->relatorio;

        return view('admin.show', compact('aluno', 'relatorio'));
    }

    public function pdf(EstagioAluno $aluno)
    {
        abort_unless(Auth::user()->podeVerAluno($aluno), 403);
        $relatorio = $aluno->relatorio;
        abort_unless($relatorio, 404);

        $relatorio->aplicarConcedente($aluno);
        $relatorio->validarConclusao($aluno);

        [$pdf] = app(RelatorioPdf::class)->gerar($aluno, $relatorio);

        return $pdf->download('relatorio-estagio-'.$aluno->id.'.pdf');
    }
}
