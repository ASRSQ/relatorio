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
        if (! Auth::attempt([...$data, 'is_admin' => true])) {
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
        $query = EstagioAluno::with('relatorio')->orderBy('nome');
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
        $totais = ['Cadastrados' => EstagioAluno::count(), 'Iniciados' => EstagioAluno::whereHas('relatorio')->count(), 'Concluídos' => EstagioAluno::whereHas('relatorio', fn ($q) => $q->whereNotNull('concluido_em'))->count()];

        return view('admin.index', compact('alunos', 'totais'));
    }

    public function importar(Request $request, ImportarAlunos $importador)
    {
        $request->validate(['arquivo' => 'required|file|max:5120|mimes:csv,txt']);
        $count = $importador->importar($request->file('arquivo')->getRealPath());

        return back()->with('ok', "$count cadastros importados/atualizados. Relatórios preservados.");
    }

    public function show(EstagioAluno $aluno)
    {
        $relatorio = $aluno->relatorio;

        return view('admin.show', compact('aluno', 'relatorio'));
    }

    public function pdf(EstagioAluno $aluno)
    {
        $relatorio = $aluno->relatorio;
        abort_unless($relatorio, 404);

        $relatorio->validarConclusao($aluno);

        [$pdf] = app(RelatorioPdf::class)->gerar($aluno, $relatorio);

        return $pdf->download('relatorio-estagio-'.$aluno->id.'.pdf');
    }
}
