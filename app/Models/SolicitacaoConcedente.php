<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitacaoConcedente extends Model
{
    protected $table = 'solicitacoes_concedente';
    protected $guarded = [];
    protected $casts = ['dados' => 'array', 'decidido_em' => 'datetime'];
    public function aluno() { return $this->belongsTo(EstagioAluno::class, 'aluno_id'); }
    public function concedente() { return $this->belongsTo(Concedente::class); }
    public function decisor() { return $this->belongsTo(User::class, 'decidido_por'); }
}
