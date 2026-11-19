# Sistema de Relatórios de Estágio Supervisionado

Aplicação Laravel 12 / PHP 8.2+ para a EEEP Monsenhor Luiz Ximenes Freire. Banco de produção: MySQL. Interface Blade responsiva, sem necessidade de Node ou Vite para as telas do sistema.

## Instalação e execução

Em uma instalação nova:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Configure DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME e DB_PASSWORD no .env. Crie previamente o banco MySQL com charset utf8mb4. Em um projeto existente, mantenha o .env e a APP_KEY atuais: o hash de identificação dos CPFs depende dessa chave.

```powershell
php artisan migrate
php artisan estagio:professor
php artisan serve
```

O comando de professor solicita nome, e-mail único e senha oculta de pelo menos 12 caracteres com letras e números. Não existe senha padrão nem cadastro público de professores. O seeder não cria contas ou alunos fictícios.

Aluno: `/`. Professor: `/admin/entrar`. Painel: `/admin`.

Em produção, configure HTTPS, APP_URL, APP_ENV=production, APP_DEBUG=false, SESSION_SECURE_COOKIE=true e SESSION_ENCRYPT=true. Aponte o servidor web para `public/` e execute `php artisan optimize`. Use cache e sessões compartilhados se houver múltiplas instâncias. Preserve a APP_KEY e faça backup do banco e da configuração em local restrito.

## Importação de cadastros

O professor pode enviar o CSV pelo painel. Também é possível usar:

```powershell
php artisan estagio:importar "caminho/para/alunos.csv"
```

CSV UTF-8, delimitado por ponto e vírgula, com cabeçalho:

```text
cpf;nascimento;nome;escola;curso;turma;ano_letivo;concedente;inicio;termino;carga_horaria;orientador
```

Datas em DD/MM/AAAA. Início e término podem ficar vazios; os demais campos são obrigatórios, exceto turma, que é opcional para manter compatibilidade com o importador anterior. CPF é validado pelos dígitos verificadores; preserve zeros à esquerda ao exportar planilhas. Carga horária: inteiro positivo até 65535. Máximo de 5000 linhas de alunos por arquivo; envio web de até 5 MB. CPFs duplicados e datas inexistentes são rejeitados. Todo o arquivo é importado em transação: uma linha inválida desfaz a operação e informa o número da linha sem exibir CPF. Reimportar atualiza os dados do aluno, preservando relatório e estado ativo/inativo. Nenhum arquivo enviado é publicado ou armazenado permanentemente pela aplicação.

## Fluxo e estados

- Não iniciado: aluno ainda não abriu o formulário.
- Iniciado: abriu o formulário, mas ainda não salvou texto.
- Preenchendo: possui salvamento e ainda não concluiu.
- Concluído: acionou Concluir relatório com os textos, identificação complementar, período e local/data preenchidos.

O aluno escreve Introdução, Atividades Desenvolvidas e Conclusão, informa local/data e pode corrigir o início e o término do seu estágio. As datas são salvas com o relatório, atualizam somente o cadastro do próprio aluno e são utilizadas no PDF. O término não pode ser anterior ao início. O navegador salva após 1,2 segundo sem digitação, mostra confirmação ou erro e tenta novamente ao recuperar conexão. Há salvamento manual e aviso de saída com alterações pendentes. O PDF aguarda o salvamento antes do download. Fechar o navegador antes da confirmação de salvamento ainda pode perder alterações; não há cópia local persistente em aparelhos compartilhados.

A versão do relatório impede sobrescrita silenciosa entre abas. Em conflito, o aluno deve copiar o texto pendente e recarregar a página. Alterar um relatório concluído devolve-o a Preenchendo; uma nova conclusão é explícita. O professor filtra por nome/turma/situação, lê os textos e baixa PDFs, inclusive rascunhos.

O PDF atual segue KAUAN.odt / KAUAN.pdf: capa com os dois logotipos originais, nome, curso, município e ano; identificação do estudante, empresa e escola em quadros; dados do estágio; Introdução e Atividades Desenvolvidas iniciando em páginas próprias; Conclusão, Referências, local/data e assinatura. Um relatório curto ocupa cinco páginas; textos maiores continuam automaticamente em novas páginas. As imagens ficam em resources/images/pdf e são incorporadas sem Internet ou dependência da pasta Downloads. Apenas os logotipos do exemplo são reutilizados; nenhum nome, empresa, texto ou referência do estudante do exemplo é cadastrado na aplicação. O mesmo template é usado por aluno e professor.

## Proteções implementadas

CSRF do Laravel; regeneração de sessão no login e invalidação no logout; limite de tentativas por IP e por identificador de conta; mensagens genéricas para credenciais incorretas; CPF armazenado como HMAC; contas administrativas com senha hash e permissão explícita `is_admin`; cadastro de professor apenas por terminal; aluno inativo bloqueado; relatório sempre obtido pelo aluno da sessão, sem aceitar identificador de aluno enviado pelo formulário; respostas privadas sem cache e proteção contra enquadramento; HTML de textos escapado; recursos remotos desabilitados no PDF.

CPF e nascimento continuam sendo os dois dados de entrada pedidos; não representam autenticação multifator. A proteção adicional implementada utiliza limites de acesso, sessões e autorização. Não há PIN adicional ou recuperação pública de conta.

## Validação

```powershell
php artisan test
php artisan route:list --except-vendor
php vendor/bin/pint --test
node tests/js/autosave.cjs
```

Os testes usam SQLite em memória, sem alterar o banco real. Cobrem login, cadastro inativo, bloqueio de conta, autorização administrativa, isolamento entre alunos, concorrência de versões, conclusão, retomada, geração de PDF e importação transacional que preserva textos. A execução em MySQL e a conferência visual em celulares devem ser realizadas no ambiente de implantação.

## Arquivos envolvidos

- `routes/web.php`: rotas de aluno e professor.
- `app/Http/Controllers/EstagioController.php` e `AdminController.php`: fluxos de aluno, PDF, autenticação administrativa, painel e importação.
- `app/Http/Middleware/Professor.php` e `PrivateResponse.php`: autorização e cabeçalhos privados.
- `app/Services/ImportarAlunos.php`: importação compartilhada pela interface e terminal.
- `app/Console/Commands/ImportarEstagiarios.php` e `CriarProfessor.php`: comandos administrativos.
- `app/Models/EstagioAluno.php`, `EstagioRelatorio.php` e `User.php`: privacidade, estados e permissão.
- `database/migrations/2026_11_17_000001_add_report_management.php`: migração incremental que preserva cadastros e textos. A migração original recebeu apenas formatação pelo Pint.
- `database/seeders/DatabaseSeeder.php`: removida criação de usuário de demonstração.
- `resources/views/estagio-login.blade.php`, `estagio-editar.blade.php` e `resources/views/admin/*`: interface.
- `.env.example`, `config/app.php`: MySQL, sessões e fuso horário.
- `tests/Feature/EstagioTest.php` e `tests/js/autosave.cjs`: testes de integração e do salvamento automático.
- `LEIA-ME.md`: documentação atualizada.

Validação do modelo atual: 17 testes PHP passaram (345 assertions), testes JavaScript do autosave passaram e Pint passou. A migração incremental 2026_11_17_000002_add_identification_to_reports foi aplicada ao banco local existente, acrescentando apenas os campos complementares. Nenhum dado de exemplo foi importado.

Correções de formulário e idioma: a versão inicial do relatório é definida no model para que a primeira abertura envie 0; o controle de concorrência continua obrigatório. Mensagens em português foram adicionadas em lang/pt_BR/validation.php, lang/pt_BR/auth.php e lang/pt_BR.json. Configure APP_LOCALE=pt_BR e APP_FALLBACK_LOCALE=pt_BR no .env; depois de alterar essas opções, execute php artisan config:clear e php artisan view:clear. Nenhuma migration adicional é necessária para editar o período do estágio.


## Dados complementares do novo modelo

A página do aluno solicita série/ano escolar e endereço, número (aceita S/N), bairro, cidade/UF, telefone e e-mail da empresa e da escola. Os dados institucionais conhecidos foram transcritos do modelo para `config/estagio.php` e aparecem preenchidos para conferência; correções do aluno valem apenas para seu relatório. Os dados pessoais e empresariais do estudante do exemplo não são utilizados como padrão. Nome, escola, curso, concedente, orientador, ano letivo e carga horária continuam vindo do cadastro importado.

A identificação complementar e as referências usam o mesmo salvamento automático, versão e isolamento do restante do relatório. Rascunhos podem ficar incompletos. Série, endereço/número/bairro/cidade de empresa e escola, três textos, início/término e local/data são exigidos ao concluir ou ao gerar o PDF do aluno. Telefones e e-mails são opcionais; e-mails preenchidos devem ser válidos. Referências são opcionais e devem citar apenas fontes realmente utilizadas. Dados opcionais ausentes são indicados como não informados no PDF. O professor pode visualizar os novos campos e baixar rascunhos incompletos. Relatórios antigos preservam seus textos e pedem complementação antes de um novo PDF pelo aluno.

Ao instalar a atualização em outro ambiente:

```powershell
php artisan migrate
php artisan config:clear
php artisan view:clear
```

Arquivos adicionados nesta atualização: `app/Http/Requests/SalvarRelatorioRequest.php`, `config/estagio.php`, `database/migrations/2026_11_17_000002_add_identification_to_reports.php`, `resources/views/estagio-identificacao.blade.php`, `resources/images/pdf/governo-ceara-capa.png` e `escola-capa.png`. Atualizados o controller/model do relatório, formulário do aluno, visualização administrativa, template PDF, traduções e testes PHP/JavaScript. A migração mantém os cadastros e os textos existentes.