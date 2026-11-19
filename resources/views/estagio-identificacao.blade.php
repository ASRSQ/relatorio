@php($identificacao = $relatorio->identificacao())
<section class="card">
    <h2>Identificação complementar</h2>
    <p>Complete os dados para o novo modelo do relatório. Você pode salvar aos poucos; os campos obrigatórios serão conferidos ao concluir ou gerar o PDF.</p>
    <label for="serie">Série / ano escolar *</label>
    <input id="serie" name="serie" maxlength="100" placeholder="Ex.: 3º ano" value="{{old('serie',$identificacao['serie'])}}">
</section>
@foreach(['empresa'=>'Empresa concedente','escola'=>'Escola'] as $prefixo=>$titulo)
<section class="card">
    <h2>{{$titulo}} — endereço e contato</h2>
    @if($prefixo === 'escola')<p>Dados preenchidos conforme o modelo institucional. Confira e corrija se necessário.</p>@endif
    <div class="footer">
        @foreach(['endereco'=>['Endereço / logradouro',255,'text'],'numero'=>['Número (ou S/N)',30,'text'],'bairro'=>['Bairro',120,'text'],'cidade'=>['Cidade / UF',120,'text'],'telefone'=>['Telefone (se houver)',30,'tel'],'email'=>['E-mail (se houver)',255,'email']] as $campo=>[$label,$limite,$tipo])
            @php($key = $prefixo.'_'.$campo)
            <div><label for="{{$key}}">{{$label}} @if(!in_array($campo,['telefone','email'])) * @endif</label><input type="{{$tipo}}" id="{{$key}}" name="{{$key}}" maxlength="{{$limite}}" value="{{old($key,$identificacao[$key])}}"></div>
        @endforeach
    </div>
</section>
@endforeach
