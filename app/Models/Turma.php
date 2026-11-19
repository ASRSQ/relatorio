<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turma extends Model
{
    protected $guarded = [];
    protected $casts = ['ativa' => 'boolean'];
    public function professores() { return $this->belongsToMany(User::class, 'professor_turma'); }
    public function alunos() { return $this->hasMany(EstagioAluno::class, 'turma_id'); }
}
