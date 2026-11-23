<?php

namespace App\Console\Commands;

use App\Services\ImportarAlunos;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportarEstagiarios extends Command
{
    protected $signature = 'estagio:importar {arquivo : CSV UTF-8} {--turma= : ID da turma existente}';

    protected $description = 'Importar alunos preservando os relatórios existentes';

    public function handle(ImportarAlunos $importador): int
    {
        $path = $this->argument('arquivo');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Arquivo inacessível.');

            return self::FAILURE;
        }
        if (! $this->option('turma') || ! \App\Models\Turma::whereKey($this->option('turma'))->exists()) {
            $this->error('Informe uma turma existente com --turma=ID.');
            return self::FAILURE;
        }
        try {
            $resultado = $importador->importar($path, ['turma_id' => $this->option('turma')]);
            $this->info('Turma: '.$resultado['turma'].'; importados: '.$resultado['importados'].'; atualizados: '.$resultado['atualizados'].'; ignorados: '.$resultado['ignorados'].'. Professores: '.(implode(', ', $resultado['professores']) ?: 'pendente')); 
        } catch (ValidationException $e) {
            foreach ($e->errors() as $errors) {
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }

return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
