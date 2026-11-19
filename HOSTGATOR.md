# Instalação e atualização em MySQL/MariaDB

Estas instruções não executam mudanças automaticamente. Confira os requisitos no plano de hospedagem e faça um ensaio em uma cópia isolada antes de aplicá-las a dados reais.

## Requisitos e preparação

- PHP 8.2 ou superior, Composer e extensões exigidas pelo Laravel e pelo DomPDF, incluindo `pdo_mysql`, `mbstring`, `dom`, `xml`, `fileinfo` e `openssl`. Confirme que o PHP usado no terminal e o usado pelo servidor web têm as extensões e versões adequadas.
- MySQL 5.7 ou posterior, ou MariaDB 10.3 ou posterior, conforme o suporte do Laravel 12. Confirme a versão efetiva do servidor; a disponibilidade depende do plano contratado.
- Banco e usuário criados no cPanel, com os privilégios necessários para criar e alterar tabelas, índices e chaves estrangeiras. Use os nomes completos fornecidos pelo cPanel.
- InnoDB, `utf8mb4` e `utf8mb4_unicode_ci`. A configuração deste projeto solicita InnoDB para novas tabelas, mas não converte tabelas existentes.
- As migrations originais criam índices sobre strings de 255 caracteres em `utf8mb4`. Não reduza esses campos automaticamente: isso mudaria limites existentes. O servidor deve suportar o tamanho dos índices, inclusive o índice composto de situação e aluno; `ROW_FORMAT=DYNAMIC` com páginas InnoDB de 8 KiB ou mais é uma configuração adequada para esses índices. Em servidores com limite de 767 bytes, solicite uma configuração compatível à hospedagem antes de instalar.
- Configure a raiz pública do domínio para a pasta `public` do projeto. Não exponha `.env`, `storage`, `vendor` ou a raiz do projeto pela web. `storage` e `bootstrap/cache` precisam ser graváveis pelo PHP.

Para inspecionar a conexão escolhida no cliente MySQL/MariaDB, sem alterar o banco:

```sql
SELECT VERSION();
SELECT DATABASE();
SHOW ENGINES;
SHOW VARIABLES LIKE 'innodb_page_size';
SHOW VARIABLES LIKE 'innodb_default_row_format';
SHOW VARIABLES LIKE 'innodb_large_prefix';
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION, ROW_FORMAT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE();
```

Algumas variáveis podem não existir em todas as versões. Em um banco existente, confira também que as tabelas relacionadas são InnoDB e que as chaves estrangeiras estão presentes. Mudar a configuração do Laravel não corrige estruturas já criadas com outro mecanismo.

## Revisão das migrations existentes

| Migration | Estruturas e pontos de atenção |
| --- | --- |
| `0001_01_01_000000_create_users_table` | Usuários, tokens e sessões. E-mail e identificadores de sessão têm índices sobre strings; `sessions.user_id` tem índice, mas não é uma chave estrangeira. |
| `0001_01_01_000001_create_cache_table` | Cache e travas; as chaves primárias são strings indexadas. |
| `0001_01_01_000002_create_jobs_table` | Filas, lotes e falhas; `queue` e `uuid` são indexados. |
| `2026_11_16_000001_create_estagio_tables` | Alunos e relatórios; CPF é um hash único, e `aluno_id` tem índice único e chave estrangeira para o aluno. |
| `2026_11_17_000001_add_report_management` | Acrescenta perfil, turma legada, situação e versão do relatório; atualiza a versão dos relatórios existentes. |
| `2026_11_17_000002_add_identification_to_reports` | Acrescenta dados de identificação e referências, sem chaves estrangeiras adicionais. |
| `2026_11_19_000001_add_gestao_estagio` | Acrescenta professores, turmas, concedentes, solicitações e snapshots JSON. Usa chave composta na tabela intermediária, chaves estrangeiras e índice composto de situação e aluno. Faz preenchimento dos snapshots e vínculos de turmas legadas. |

A ordem por nome cria as tabelas referenciadas antes das respectivas chaves. Os IDs e `foreignId` usam inteiros grandes sem sinal no MySQL/MariaDB; os campos de referências opcionais usam `nullable` antes de `nullOnDelete`. Booleanos, campos de data, texto e JSON são gerados pelo esquema do Laravel para o banco selecionado. Nenhuma migration contém `PRAGMA` ou SQL específico de SQLite. A compatibilidade de JSON, índices e chaves ainda deve ser testada na versão e configuração reais da hospedagem. As migrations antigas foram mantidas intactas; nenhuma migration adicional é necessária para selecionar InnoDB em tabelas novas.

## Configuração

Copie os valores de `.env.hostgator.example` para o `.env` do servidor e preencha URL e credenciais locais. Não envie o `.env` ao repositório. Use `DB_CONNECTION=mysql` ou `DB_CONNECTION=mariadb` conforme o servidor. O host e a porta devem ser confirmados no cPanel.

Para uma instalação nova, gere `APP_KEY` uma única vez. **Em uma instalação existente, preserve exatamente a chave anterior:** os hashes de CPF usados no login dependem dela, assim como dados criptografados e sessões. Não execute `key:generate` ao atualizar uma instalação existente.

O exemplo usa sessões e cache no banco; as migrations já incluem `sessions`, `cache` e `cache_locks`. A fila está configurada como `sync`, adequada quando não há worker permanente, mas tarefas executadas nela aumentam o tempo da requisição. Para usar `QUEUE_CONNECTION=database`, mantenha um worker confiável e configure seu reinício; as tabelas `jobs`, `job_batches` e `failed_jobs` já fazem parte das migrations. Cache de arquivo é uma alternativa para instalação em um único servidor, desde que `storage/framework/cache/data` seja gravável.

## Instalação nova

Com o diretório público e o `.env` configurados, e **somente em um banco novo e vazio**, os comandos abaixo podem ser executados pelo responsável no servidor:

```sh
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate:status
php artisan migrate --force
php artisan config:cache
```

Execute `key:generate` apenas se a instalação for nova e ainda não tiver uma chave. Não execute `composer run setup`: o script deste projeto também gera chave e executa migrations automaticamente.

## Atualização de instalação existente

1. Faça backup verificável do banco e dos arquivos necessários, inclusive da `APP_KEY`, e confirme como restaurá-los. Desative temporariamente as gravações durante a atualização.
2. Em uma cópia isolada da mesma versão de MySQL/MariaDB, teste o backup, o código novo e as migrations pendentes. Verifique dados, vínculos, login, sessões, cache, CPF, relatórios e PDF.
3. Confira `php artisan migrate:status` no destino. Não apague nem altere registros da tabela `migrations`. A migration de gestão faz várias alterações de esquema e preenchimentos: se falhar em MySQL/MariaDB, parte pode já ter sido aplicada. Não repita cegamente; investigue e use o backup.
4. Depois do ensaio e da cópia de segurança, o responsável poderá rodar `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force` e `php artisan config:cache` no diretório da aplicação.
5. Verifique as contagens de alunos e relatórios, os vínculos, login e geração de PDF. Se houver worker de fila, reinicie-o conforme o método disponível no plano.

`php artisan migrate --pretend` pode ajudar a examinar o SQL em um ambiente de teste, mas não prova que a migration funcionará com os dados reais nem demonstra necessariamente as consultas condicionais de preenchimento. Não use `migrate:fresh`, `migrate:refresh`, `db:wipe` ou rollback para atualizar dados reais.

## Dados vindos de SQLite

Um arquivo SQLite não pode ser usado diretamente como banco MySQL/MariaDB. A migração de dados exige uma conversão e importação separadas, testadas em ambiente isolado, preservando IDs, relacionamentos, a tabela `migrations` coerente com o esquema realmente importado e a `APP_KEY`. MySQL/MariaDB podem comparar textos de forma diferente do SQLite: valores de e-mail ou outros identificadores considerados distintos na origem podem colidir em índices únicos com a collation do destino. Verifique e resolva essas colisões sem excluir dados antes da importação. A configuração da conexão não transfere dados por si só.
