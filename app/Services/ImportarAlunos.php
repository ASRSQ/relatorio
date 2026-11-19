<?php

namespace App\Services;

use App\Http\Controllers\EstagioController;
use App\Models\EstagioAluno;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportarAlunos
{
    public function importar(string $path): int
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
            $required = ['cpf', 'nascimento', 'nome', 'escola', 'curso', 'ano_letivo', 'concedente', 'inicio', 'termino', 'carga_horaria', 'orientador'];
            if (array_diff($required, $header) || count(array_unique($header)) !== count($header)) {
                throw ValidationException::withMessages(['arquivo' => 'Cabeçalho inválido. Consulte as colunas no manual.']);
            }

            return DB::transaction(function () use ($fp, $header) {
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
                    if (isset($seen[$cpf])) {
                        $fail('CPF repetido no arquivo.');
                    }
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
                    $rules = array_fill_keys(['nome', 'escola', 'curso', 'concedente', 'orientador'], 'required|string|max:255');
                    $rules += ['nascimento' => 'required|date|before:today', 'inicio' => 'nullable|date', 'termino' => 'nullable|date|after_or_equal:inicio', 'ano_letivo' => 'required|string|max:10', 'carga_horaria' => 'required|integer|min:1|max:65535', 'turma' => 'nullable|string|max:100'];
                    $validator = Validator::make($row, $rules);
                    if ($validator->fails()) {
                        $fail(implode(' ', $validator->errors()->all()));
                    }
                    EstagioAluno::updateOrCreate(['cpf_hash' => hash_hmac('sha256', $cpf, config('app.key'))], $validator->validated());
                    $count++;
                }
                if (! $count) {
                    throw ValidationException::withMessages(['arquivo' => 'O CSV não contém alunos.']);
                }

                return $count;
            });
        } finally {
            fclose($fp);
        }
    }
}
