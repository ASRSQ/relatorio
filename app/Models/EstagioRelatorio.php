<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

class EstagioRelatorio extends Model
{
    public const MINIMOS_TEXTO = ['introducao' => 800, 'atividades' => 1200, 'conclusao' => 600];
    public const MAXIMOS_TEXTO = ['introducao' => 2500, 'atividades' => 2200, 'conclusao' => 2000];

    protected $table = 'estagio_relatorios';

    protected $guarded = [];

    protected $attributes = ['versao' => 0];

    protected $casts = ['data_relatorio' => 'date', 'concluido_em' => 'datetime', 'versao' => 'integer'];

    public function identificacao(): array
    {
        $dados = ['serie' => $this->serie];
        foreach (['empresa', 'escola'] as $prefixo) {
            foreach (['endereco', 'numero', 'bairro', 'cidade', 'telefone', 'email'] as $campo) {
                $key = $prefixo.'_'.$campo;
                $dados[$key] = $this->$key ?? ($prefixo === 'escola' ? config('estagio.escola.'.$campo) : null);
            }
        }

        return $dados;
    }

    public function validarConclusao(EstagioAluno $aluno): void
    {
        $dados = array_merge($this->identificacao(), $this->only(['introducao', 'atividades', 'conclusao', 'local', 'data_relatorio']), [
            'inicio' => $aluno->inicio?->format('Y-m-d'), 'termino' => $aluno->termino?->format('Y-m-d'),
        ]);
        $rules = array_fill_keys([
            'serie', 'empresa_endereco', 'empresa_numero', 'empresa_bairro', 'empresa_cidade',
            'escola_endereco', 'escola_numero', 'escola_bairro', 'escola_cidade',
            'introducao', 'atividades', 'conclusao', 'local',
        ], 'required');
        $rules += ['inicio' => 'required|date', 'termino' => 'required|date|after_or_equal:inicio', 'data_relatorio' => 'required|date'];
        foreach (self::MINIMOS_TEXTO as $campo => $minimo) {
            $maximo = self::MAXIMOS_TEXTO[$campo];
            $rules[$campo] = ['required', function ($attribute, $value, $fail) use ($minimo, $maximo) {
                $normalizado = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $value ?? ''));
                $faltam = $minimo - mb_strlen($normalizado, 'UTF-8');
                if ($faltam > 0) {
                    $fail("Faltam {$faltam} caracteres para atingir o mínimo de {$minimo} nesta seção.");
                }
                if (mb_strlen(trim($value ?? ''), 'UTF-8') > $maximo) {
                    $fail("Esta seção ultrapassa o máximo de {$maximo} caracteres. Reduza o texto.");
                }
            }];
        }
        Validator::make($dados, $rules)->validate();
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(EstagioAluno::class, 'aluno_id');
    }

    public function getSituacaoAttribute(): string
    {
        return $this->concluido_em ? 'Concluído' : ($this->versao > 0 ? 'Preenchendo' : 'Iniciado');
    }
}
