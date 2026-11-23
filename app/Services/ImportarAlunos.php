<?php

namespace App\Services;

use App\Http\Controllers\EstagioController;
use App\Models\EstagioAluno;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportarAlunos
{
    public function importar(string $path, ?array $opcoes = null, ?\App\Models\User $usuario = null): int|array
    {
        $fp = fopen($path, 'r');
        if (! $fp) {
            throw ValidationException::withMessages(['arquivo' => 'Não foi possível abrir o CSV.']);
        }
        try {
            $header = fgetcsv($fp, 0, ';', '"', '');
            if (! $header) {
                throw ValidationException::withMessages(['arquivo' => 'CSV vazio.']);
            }
            $header = array_map(fn ($v) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $v ?? '')), $header);
            $required = ['cpf', 'nascimento', 'nome', 'escola', 'curso', 'ano_letivo', 'inicio', 'termino', 'carga_horaria', 'orientador'];
            if (array_diff($required, $header) || count(array_unique($header)) !== count($header)) {
                throw ValidationException::withMessages(['arquivo' => 'Cabeçalho inválido. Consulte as colunas no manual.']);
            }

            return DB::transaction(function () use ($fp, $header, $opcoes, $usuario) {
                $turmaSelecionada = null; $criada = false; $importados = 0; $atualizados = 0; $ignorados = 0;
                if ($opcoes !== null) {
                    if (! empty($opcoes['turma_id'])) {
                        $turmaSelecionada = \App\Models\Turma::findOrFail($opcoes['turma_id']);
                        if ($usuario && ! $usuario->is_admin && ! $usuario->turmas()->where('turmas.id', $turmaSelecionada->id)->exists()) abort(403);
                    } else {
                        $identidade = array_intersect_key($opcoes, array_flip(['escola', 'curso', 'serie', 'ano_letivo', 'nome']));
                        $chave = \App\Models\Turma::chaveIdentidade($identidade);
                        $turmaSelecionada = \App\Models\Turma::where('identidade_hash', $chave)->first();
                        if (! $turmaSelecionada) {
                            $turmaSelecionada = \App\Models\Turma::where($identidade)->first();
                            if ($turmaSelecionada) { $turmaSelecionada->identidade_hash = $chave; $turmaSelecionada->save(); }
                            else $turmaSelecionada = \App\Models\Turma::firstOrCreate(['identidade_hash' => $chave], $identidade);
                        }
                        $criada = $turmaSelecionada->wasRecentlyCreated;
                        if ($usuario && ! $usuario->is_admin) {
                            if (! $criada && ! $usuario->turmas()->where('turmas.id', $turmaSelecionada->id)->exists()) abort(403);
                            if ($criada) $turmaSelecionada->professores()->syncWithoutDetaching([$usuario->id]);
                        }
                    }
                }
                $count = 0;
                $line = 1;
                $seen = [];
                while (($values = fgetcsv($fp, 0, ';', '"', '')) !== false) {
                    $line++;
                    if ($values === [null]) {
                        continue;
                    }
                    $fail = fn ($message) => throw ValidationException::withMessages(['arquivo' => "Linha $line: $message Nenhum cadastro foi alterado."]);
                    if ($line > 5001) {
                        $fail('Máximo de 5000 alunos por arquivo.');
                    }
                    if (count($values) !== count($header)) {
                        $fail('Quantidade de colunas inválida.');
                    }
                    $row = array_map(fn ($v) => trim($v ?? ''), array_combine($header, $values));
                    foreach ($row as $value) {
                        if (! mb_check_encoding($value, 'UTF-8')) {
                            $fail('Use codificação UTF-8.');
                        }
                    }
                    $cpf = preg_replace('/\D/', '', $row['cpf']);
                    if (! EstagioController::cpfValido($cpf)) {
                        $fail('CPF inválido.');
                    }
                    if (isset($seen[$cpf])) { $ignorados++; continue; }
                    $seen[$cpf] = true;
                    foreach (['nascimento', 'inicio', 'termino'] as $field) {
                        if ($row[$field] === '' && $field !== 'nascimento') {
                            $row[$field] = null;

                            continue;
                        }
                        $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $row[$field]);
                        if (! $date || $date->format('d/m/Y') !== $row[$field]) {
                            $fail("Data inválida em $field; use DD/MM/AAAA.");
                        }
                        $row[$field] = $date->format('Y-m-d');
                    }
                    $rules = array_fill_keys(['nome', 'escola', 'curso', 'orientador'], 'required|string|max:255');
                    if (array_key_exists('concedente', $row) && $row['concedente'] !== '') $rules['concedente'] = 'string|max:255';
                    $rules += ['nascimento' => 'required|date|before:today', 'inicio' => 'nullable|date', 'termino' => 'nullable|date|after_or_equal:inicio', 'ano_letivo' => 'required|string|max:10', 'carga_horaria' => 'required|integer|min:1|max:65535', 'turma' => 'nullable|string|max:100'];
                    $validator = Validator::make($row, $rules);
                    if ($validator->fails()) {
                        $fail(implode(' ', $validator->errors()->all()));
                    }
                    $dados = $validator->validated();
                    if ($turmaSelecionada) {
                        foreach (['escola', 'curso', 'ano_letivo'] as $campo) {
                            if ($turmaSelecionada->$campo && mb_strtolower(trim($dados[$campo])) !== mb_strtolower(trim($turmaSelecionada->$campo))) $fail("O campo $campo não corresponde à turma selecionada.");
                        }
                        $dados['turma'] = $turmaSelecionada->nome;
                        $dados['turma_id'] = $turmaSelecionada->id;
                    } elseif (! empty($dados['turma'])) {
                        $turma = \App\Models\Turma::firstOrCreate(['nome'=>$dados['turma'], 'curso'=>$dados['curso'], 'ano_letivo'=>$dados['ano_letivo']]);
                        $dados['turma_id'] = $turma->id;
                    }
                    $hash = hash_hmac('sha256', $cpf, config('app.key'));
                    $aluno = EstagioAluno::where('cpf_hash', $hash)->first();
                    if ($aluno) {
                        if ($usuario && ! $usuario->is_admin && ! $usuario->podeVerAluno($aluno)) $fail('Um aluno já cadastrado não pertence às suas turmas.');
                        unset($dados['concedente']);
                        $aluno->fill($dados)->save(); $atualizados++;
                    } else {
                        $dados['concedente'] ??= '';
                        EstagioAluno::create(['cpf_hash' => $hash] + $dados); $importados++;
                    }
                    $count++;
                }
                if (! $count) {
                    throw ValidationException::withMessages(['arquivo' => 'O CSV não contém alunos.']);
                }

                if ($opcoes === null) return $count;
                return ['turma' => $turmaSelecionada->nome, 'criada' => $criada, 'importados' => $importados, 'atualizados' => $atualizados, 'ignorados' => $ignorados, 'professores' => $turmaSelecionada->professores()->pluck('name')->all()];
            });
        } finally {
            fclose($fp);
        }
    }
}
