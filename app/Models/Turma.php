<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turma extends Model
{
    protected $guarded = [];
    protected $casts = ['ativa' => 'boolean'];
    public static function chaveIdentidade(array $dados): string
    {
        return hash('sha256', json_encode(array_map(fn ($campo) => mb_strtolower(trim($dados[$campo] ?? '')), ['escola', 'curso', 'serie', 'ano_letivo', 'nome']), JSON_UNESCAPED_UNICODE));
    }
    public function professores() { return $this->belongsToMany(User::class, 'professor_turma'); }
    public function alunos() { return $this->hasMany(EstagioAluno::class, 'turma_id'); }
}
