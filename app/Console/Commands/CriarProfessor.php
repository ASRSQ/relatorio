<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CriarProfessor extends Command
{
    protected $signature = 'estagio:professor';

    protected $description = 'Criar acesso administrativo para um professor';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Nome'), 'email' => $this->ask('E-mail'), 'password' => $this->secret('Senha (mínimo 12 caracteres, letras e números)')];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => ['required', Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = new User($data);
        $user->is_admin = true;
        $user->save();
        $this->info('Professor cadastrado. Acesse /admin/entrar.');

        return self::SUCCESS;
    }
}
