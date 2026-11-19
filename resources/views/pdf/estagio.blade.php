<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório Final de Estágio Supervisionado</title>
    <style>
        @page { size: A4 portrait; margin: 30mm 20mm 20mm 30mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 11.5pt; line-height: 1.5; color: #000; }
        .capa { position: relative; height: 237mm; page-break-after: always; text-align: center; font-size: 12pt; font-weight: bold; }
        .logo-governo { position: absolute; top: -22mm; left: -10mm; width: 36.4mm; height: 27.4mm; }
        .logo-escola { position: absolute; top: -22mm; right: 4mm; width: 26mm; height: 26mm; }
        .instituicao { position: absolute; top: 11mm; left: 15mm; right: 15mm; line-height: 1.35; }
        .nome-capa { position: absolute; top: 55mm; left: 0; right: 0; overflow-wrap: break-word; word-wrap: break-word; }
        .titulo-capa { position: absolute; top: 114mm; left: 0; right: 0; }
        .local-capa { position: absolute; top: 219mm; left: 0; right: 0; line-height: 1.2; }
        h1 { margin: 0 10mm 9mm -10mm; font-size: 12pt; font-weight: normal; text-align: center; line-height: 1.3; }
        h2 { margin: 0 0 9mm; font-size: 12pt; font-weight: normal; line-height: 1.3; page-break-after: avoid; }
        .ident-bloco { width: 169mm; border: .6pt solid #000; padding: 1.5mm 1mm; margin-top: -.6pt; margin-left: -9.5mm; page-break-inside: avoid; font-weight: bold; }
        .ident-bloco h2 { margin: 0 0 4mm; font-size: 11.5pt; }
        .ident-bloco p { margin: 0 0 5mm; overflow-wrap: break-word; word-wrap: break-word; }
        .ident-bloco p:last-child { margin-bottom: 1mm; } .estudante p { margin-bottom: 2mm; } .estudante h2 { margin-bottom: 1.5mm; } .dados-estagio { padding-bottom: 12mm; }
        .linha-dupla { width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 5mm; }
        .linha-dupla td { vertical-align: top; padding: 0; word-wrap: break-word; overflow-wrap: break-word; }
        .principal { width: 65%; }
        .nova-pagina { page-break-before: always; }
        .texto { margin: 0 0 2mm; text-indent: 12.5mm; text-align: justify; font-size: 12pt; font-weight: bold; line-height: 1.5; overflow-wrap: break-word; word-wrap: break-word; orphans: 3; widows: 3; }
        .sumario-linha { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        .sumario-linha td { vertical-align: bottom; }
        .sumario-pontos { border-bottom: 1px dotted #000; width: 100%; }
        .sumario-numero { text-align: right; padding-left: 2mm; }
        .referencias .texto { text-indent: 0; }
        .fechamento { margin-top: 10mm; page-break-inside: avoid; text-align: right; font-weight: bold; }
        .assinatura { padding-top: 30mm; width: 65%; margin-left: auto; text-align: center; }
        .assinatura-linha { border-top: .6pt solid #000; padding-top: 2mm; }
    </style>
</head>
<body>
@php($identificacao = $relatorio->identificacao())
@php($temReferencias = (bool) preg_match('/[^\s\p{Z}]/u', $relatorio->referencias ?? ''))
@php($paginas = $paginas ?? [])
@php($cursoCapa = preg_replace('/^(?:curso\s+)?t[ée]cnico\s+em\s+/iu', '', $aluno->curso ?? ''))
    <div class="capa" id="secao-capa">
        <img class="logo-governo" src="data:image/png;base64,{{base64_encode(file_get_contents(resource_path('images/pdf/governo-ceara-capa.png')))}}" alt="Governo do Estado do Ceará — Secretaria da Educação">
        <img class="logo-escola" src="data:image/png;base64,{{base64_encode(file_get_contents(resource_path('images/pdf/escola-capa.png')))}}" alt="EEEP Monsenhor Luís Ximenes Freire">
        <div class="instituicao">COORDENADORIA DE EDUCAÇÃO PROFISSIONAL – COEDP<br>CÉLULA DE ESTÁGIOS – CEEST<br>{{mb_strtoupper($aluno->escola ?? '')}}<br>CURSO TÉCNICO EM {{mb_strtoupper($cursoCapa)}}</div>
        <div class="nome-capa">{{mb_strtoupper($aluno->nome ?? '')}}</div>
        <div class="titulo-capa">RELATÓRIO FINAL DE ESTÁGIO SUPERVISIONADO</div>
        <div class="local-capa">{{$relatorio->local ?: 'Local não informado'}}<br>{{$aluno->ano_letivo}}</div>
    </div>
    <div id="secao-identificacao">
    <h1>RELATÓRIO FINAL DE ESTÁGIO SUPERVISIONADO</h1>
    <div class="ident-bloco estudante">
        <h2>IDENTIFICAÇÃO DO ESTUDANTE</h2>
        <p>Nome: {{$aluno->nome}}</p><p>Curso: {{$aluno->curso}}</p><p>Série: {{$identificacao['serie'] ?: 'Não informada'}}</p>
    </div>
    <div class="ident-bloco">
        <h2>IDENTIFICAÇÃO DA EMPRESA</h2>
        <p>Nome da Empresa: {{$aluno->concedente}}</p>
        <table class="linha-dupla"><tr><td class="principal">Endereço: {{$identificacao['empresa_endereco'] ?: 'Não informado'}}</td><td>Nº: {{$identificacao['empresa_numero'] ?: 'Não informado'}}</td></tr></table>
        <p>Bairro: {{$identificacao['empresa_bairro'] ?: 'Não informado'}}</p>
        <table class="linha-dupla"><tr><td class="principal">Cidade: {{$identificacao['empresa_cidade'] ?: 'Não informada'}}</td><td>Fone: {{$identificacao['empresa_telefone'] ?: 'Não informado'}}</td></tr></table>
        <p>E-mail: {{$identificacao['empresa_email'] ?: 'Não informado'}}</p>
    </div>
    <div class="ident-bloco">
        <h2>IDENTIFICAÇÃO DA ESCOLA</h2>
        <p>Nome: {{$aluno->escola}}</p>
        <p>Endereço: {{$identificacao['escola_endereco'] ?: 'Não informado'}} — Nº: {{$identificacao['escola_numero'] ?: 'Não informado'}}</p>
        <p>Bairro: {{$identificacao['escola_bairro'] ?: 'Não informado'}}</p>
        <table class="linha-dupla"><tr><td class="principal">Cidade: {{$identificacao['escola_cidade'] ?: 'Não informada'}}</td><td>Fone: {{$identificacao['escola_telefone'] ?: 'Não informado'}}</td></tr></table>
        <p>E-mail: {{$identificacao['escola_email'] ?: 'Não informado'}}</p>
    </div>
    <div class="ident-bloco dados-estagio">
        <h2>DADOS DO ESTÁGIO</h2>
        <p>Área de estágio (curso): {{$aluno->curso}}</p>
        <table class="linha-dupla"><tr><td>Data de início: {{$aluno->inicio?->format('d/m/Y') ?: 'Não informada'}}</td><td>Data de término: {{$aluno->termino?->format('d/m/Y') ?: 'Não informada'}}</td></tr></table>
        <p>Duração: {{$aluno->carga_horaria}} horas</p><p>Orientador(a) de estágio: {{$aluno->orientador}}</p>
    </div>
    </div>

    <div class="nova-pagina" id="secao-sumario">
        <h2>SUMÁRIO</h2>
        @foreach(array_filter(['introducao' => 'INTRODUÇÃO', 'atividades' => 'ATIVIDADES DESENVOLVIDAS', 'conclusao' => 'CONCLUSÃO', 'referencias' => $temReferencias ? 'REFERÊNCIAS' : null]) as $campo => $titulo)
        <table class="sumario-linha"><tr><td style="white-space: nowrap">{{$titulo}}</td><td class="sumario-pontos"></td><td class="sumario-numero">{{$paginas[$campo] ?? '–'}}</td></tr></table>
        @endforeach
    </div>

    @foreach(['introducao'=>'INTRODUÇÃO','atividades'=>'ATIVIDADES DESENVOLVIDAS','conclusao'=>'CONCLUSÃO'] as $campo=>$titulo)
        <div class="nova-pagina" id="secao-{{$campo}}">
            <h2>{{$titulo}}</h2>
            @foreach(preg_split('/\R(?:\h*\R)*/u',$relatorio->$campo ?? '') as $paragrafo)
                <p class="texto" data-secao="{{$campo}}">@if(trim($paragrafo)==='')<br>@else{{$paragrafo}}@endif</p>
            @endforeach
            @if($campo === 'conclusao' && ! $temReferencias)
            <div class="fechamento" data-secao="conclusao">
                {{$relatorio->local ?: 'Local não informado'}}, {{$relatorio->data_relatorio?->format('d/m/Y') ?: 'Data não informada'}}.
                <div class="assinatura"><div class="assinatura-linha">{{$aluno->nome}}</div></div>
            </div>
            @endif
        </div>
    @endforeach
    @if($temReferencias)
    <div class="nova-pagina referencias" id="secao-referencias">
        <h2>REFERÊNCIAS</h2>
        @foreach(preg_split('/\R/u',$relatorio->referencias) as $paragrafo)
            <p class="texto" data-secao="referencias">@if(trim($paragrafo)==='')<br>@else{{$paragrafo}}@endif</p>
        @endforeach
        <div class="fechamento" data-secao="referencias">
            {{$relatorio->local ?: 'Local não informado'}}, {{$relatorio->data_relatorio?->format('d/m/Y') ?: 'Data não informada'}}.
            <div class="assinatura"><div class="assinatura-linha">{{$aluno->nome}}</div></div>
        </div>
    </div>
    @endif
</body>
</html>
