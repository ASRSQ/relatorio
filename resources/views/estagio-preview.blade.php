<!doctype html>
<html lang="pt-BR">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pré-visualização do relatório</title>
<style>body{font:16px system-ui,sans-serif;margin:0;color:#17382b;background:#f3f7f5}main{max-width:1000px;margin:auto;padding:16px}.aviso{background:#fff3cf;padding:12px;border-radius:8px}iframe{width:100%;height:75vh;border:1px solid #b8c9bd}a{color:#12633b}</style></head>
<body><main><h1>Pré-visualização do relatório</h1><p>Esta é uma visualização do rascunho salvo. Confira o conteúdo e a paginação antes de finalizar.</p>
@if($erros)<div class="aviso" role="alert"><strong>Revise a paginação:</strong><ul>@foreach($erros as $erro)<li>{{$erro}}</li>@endforeach</ul></div>@else<p>A verificação de paginação não encontrou seções que ultrapassem uma página.</p>@endif
<p><a href="{{route('estagio.editar')}}">Voltar ao relatório</a></p>
<iframe title="Pré-visualização do relatório em PDF" src="data:application/pdf;base64,{{$pdfBase64}}"></iframe>
<p><a href="data:application/pdf;base64,{{$pdfBase64}}" download="previsualizacao-relatorio.pdf">Baixar pré-visualização</a></p></main></body></html>
