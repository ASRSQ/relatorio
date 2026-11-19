<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EstagioAluno extends Model
{
    protected $table = 'estagio_alunos';

    protected $guarded = [];

    protected $hidden = ['cpf_hash', 'nascimento'];

    protected $casts = ['nascimento' => 'date', 'inicio' => 'date', 'termino' => 'date', 'ativo' => 'boolean'];

    public function relatorio(): HasOne
    {
        return $this->hasOne(EstagioRelatorio::class, 'aluno_id');
    }
}
