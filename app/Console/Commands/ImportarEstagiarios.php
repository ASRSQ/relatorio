<?php

namespace App\Console\Commands;

use App\Services\ImportarAlunos;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportarEstagiarios extends Command
{
    protected $signature = 'estagio:importar {arquivo : CSV UTF-8 separado por ponto e vírgula}';

    protected $description = 'Importar alunos preservando os relatórios existentes';

    public function handle(ImportarAlunos $importador): int
    {
        $path = $this->argument('arquivo');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Arquivo inacessível.');

            return self::FAILURE;
        }
        try {
            $this->info('Importados/atualizados: '.$importador->importar($path));
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
