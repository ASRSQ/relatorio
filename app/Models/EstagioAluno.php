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

    public function turmaVinculada() { return $this->belongsTo(Turma::class, 'turma_id'); }
    public function empresa() { return $this->belongsTo(Concedente::class, 'concedente_id'); }
    public function solicitacoes() { return $this->hasMany(SolicitacaoConcedente::class, 'aluno_id'); }
}
