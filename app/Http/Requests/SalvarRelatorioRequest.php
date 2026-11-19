<?php

namespace App\Http\Requests;

use App\Models\EstagioAluno;
use Illuminate\Foundation\Http\FormRequest;

class SalvarRelatorioRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(EstagioAluno::whereKey($this->session()->get('estagio_aluno_id'))->where('ativo', true)->exists(), 403, 'Acesso não autorizado. Retorne à página de entrada.');

        return true;
    }

    public function rules(): array
    {
        $rules = [
            'introducao' => 'nullable|string|max:15000', 'atividades' => 'nullable|string|max:30000',
            'conclusao' => 'nullable|string|max:15000', 'referencias' => 'nullable|string|max:10000',
            'local' => 'nullable|string|max:120', 'data_relatorio' => 'nullable|date_format:Y-m-d',
            'inicio' => 'nullable|date_format:Y-m-d', 'termino' => 'nullable|date_format:Y-m-d',
            'serie' => 'nullable|string|max:100', 'versao' => 'required|integer|min:0', 'concluir' => 'nullable|boolean',
        ];
        foreach (['empresa', 'escola'] as $prefixo) {
            $rules[$prefixo.'_endereco'] = 'nullable|string|max:255';
            $rules[$prefixo.'_numero'] = 'nullable|string|max:30';
            $rules[$prefixo.'_bairro'] = 'nullable|string|max:120';
            $rules[$prefixo.'_cidade'] = 'nullable|string|max:120';
            $rules[$prefixo.'_telefone'] = 'nullable|string|max:30';
            $rules[$prefixo.'_email'] = 'nullable|email|max:255';
        }

        return $rules;
    }
}
