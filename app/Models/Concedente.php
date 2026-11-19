<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Concedente extends Model
{
    protected $guarded = [];
    protected $casts = ['ativa' => 'boolean'];
    public function alunos() { return $this->hasMany(EstagioAluno::class, 'concedente_id'); }
}
