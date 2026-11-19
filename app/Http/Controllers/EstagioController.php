<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarRelatorioRequest;
use App\Models\EstagioAluno;
use App\Services\RelatorioPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EstagioController extends Controller
{
    private function aluno(Request $request): EstagioAluno
    {
        $id = $request->session()->get('estagio_aluno_id');
        $aluno = $id ? EstagioAluno::find($id) : null;
        if (! $aluno || ! $aluno->ativo) {
            $request->session()->forget('estagio_aluno_id');
            abort(403, 'Acesso não autorizado. Retorne à página de entrada.');
        }

        return $aluno;
    }

    public function loginForm(Request $request)
    {
        if ($request->session()->has('estagio_aluno_id')) {
            return redirect()->route('estagio.editar');
        }

        return view('estagio-login');
    }

    public function login(Request $request)
    {
        $dados = $request->validate(['cpf' => ['required', 'string', 'max:20'], 'nascimento' => ['required', 'date_format:Y-m-d']]);
        $cpf = preg_replace('/\D/', '', $dados['cpf']);
        $hash = hash_hmac('sha256', $cpf, config('app.key'));
        $key = 'aluno:'.$hash;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['cpf' => 'Aguarde alguns minutos antes de tentar novamente.']);
        }
        RateLimiter::hit($key, 300);
        $aluno = self::cpfValido($cpf) ? EstagioAluno::where('cpf_hash', $hash)->where('ativo', true)->first() : null;
        if (! $aluno || $aluno->nascimento->format('Y-m-d') !== $dados['nascimento']) {
            throw ValidationException::withMessages(['cpf' => 'Dados de acesso não encontrados.']);
        }
        RateLimiter::clear($key);
        Auth::logout();
        $request->session()->regenerate();
        $request->session()->put('estagio_aluno_id', $aluno->id);

        return redirect()->route('estagio.editar');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('estagio.login');
    }

    public function edit(Request $request)
    {
        $aluno = $this->aluno($request);
        $relatorio = $aluno->relatorio()->firstOrCreate([], ['local' => 'Santa Quitéria']);

        return view('estagio-editar', compact('aluno', 'relatorio'));
    }

    public function save(SalvarRelatorioRequest $request)
    {
        $aluno = $this->aluno($request);
        $dados = $request->validated();
        $relatorio = DB::transaction(function () use ($aluno, $dados) {
            $relatorio = $aluno->relatorio()->lockForUpdate()->firstOrFail();
            abort_if($relatorio->versao !== (int) $dados['versao'], 409, 'O relatório foi alterado em outra aba. Copie seu texto e recarregue a página.');
            $aluno->refresh();
            $periodo = [
                'inicio' => array_key_exists('inicio', $dados) ? $dados['inicio'] : $aluno->inicio?->format('Y-m-d'),
                'termino' => array_key_exists('termino', $dados) ? $dados['termino'] : $aluno->termino?->format('Y-m-d'),
            ];
            Validator::make($periodo, [
                'termino' => array_filter(['nullable', 'date_format:Y-m-d', $periodo['inicio'] ? 'after_or_equal:inicio' : null]),
            ])->validate();
            $concluir = $dados['concluir'] ?? false;
            unset($dados['versao'], $dados['concluir'], $dados['inicio'], $dados['termino']);
            $relatorio->fill($dados);
            $aluno->fill($periodo);
            if ($concluir) {
                $relatorio->validarConclusao($aluno);
                app(RelatorioPdf::class)->gerar($aluno, $relatorio);
            }
            $aluno->save();
            $relatorio->concluido_em = $concluir ? now() : null;
            $relatorio->versao++;
            $relatorio->save();

            return $relatorio;
        });
        if ($request->expectsJson()) {
            return response()->json(['versao' => $relatorio->versao, 'salvo_em' => $relatorio->updated_at->toIso8601String(), 'concluido' => (bool) $relatorio->concluido_em]);
        }

        return redirect()->route('estagio.editar')->with('ok', 'Relatório salvo com sucesso.');
    }

    public function pdf(Request $request)
    {
        $aluno = $this->aluno($request);
        $relatorio = $aluno->relatorio;
        if (! $relatorio || ! trim($relatorio->introducao ?? '') || ! trim($relatorio->atividades ?? '') || ! trim($relatorio->conclusao ?? '')) {
            return redirect()->route('estagio.editar')->withErrors(['pdf' => 'Preencha e salve as três seções antes de gerar o PDF.']);
        }
        $relatorio->validarConclusao($aluno);

        [$pdf] = app(RelatorioPdf::class)->gerar($aluno, $relatorio);

        return $pdf->download('relatorio-estagio-'.$aluno->id.'.pdf');
    }

    public function preview(Request $request)
    {
        $aluno = $this->aluno($request);
        $relatorio = $aluno->relatorio()->firstOrCreate([], ['local' => 'Santa Quitéria']);
        [$pdf, $erros] = app(RelatorioPdf::class)->gerar($aluno, $relatorio, false);

        return response()->view('estagio-preview', ['pdfBase64' => base64_encode($pdf->output()), 'erros' => $erros]);
    }

    public static function cpfValido(string $cpf): bool
    {
        if (! preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $dig = (10 * $soma) % 11;
            if ($dig === 10) {
                $dig = 0;
            }
            if ((int) $cpf[$t] !== $dig) {
                return false;
            }
        }

        return true;
    }
}
