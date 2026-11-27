@foreach(['nome'=>'Nome da empresa *','nome_fantasia'=>'Nome fantasia (opcional)','documento'=>'CPF ou CNPJ (opcional)','endereco'=>'Endereço (opcional)','numero'=>'Número (opcional)','bairro'=>'Bairro (opcional)','cidade'=>'Cidade (opcional)','estado'=>'Estado / UF (opcional)','telefone'=>'Telefone (opcional)','email'=>'E-mail (opcional)','responsavel'=>'Responsável (opcional)'] as $campo=>$rotulo)
<label>{{$rotulo}}
@if($campo === 'estado')
<select name="estado"><option value="">Selecione a UF</option>
@foreach(['AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia','CE'=>'Ceará','DF'=>'Distrito Federal','ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais','PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco','PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte','RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe','TO'=>'Tocantins'] as $uf=>$nomeEstado)
<option value="{{$uf}}" @selected(strtoupper(old('estado', $empresa->estado ?? '')) === $uf)>{{$uf}} — {{$nomeEstado}}</option>
@endforeach
</select>
@else
<input name="{{$campo}}" value="{{old($campo, $empresa->$campo ?? '')}}" @required($campo === 'nome') @if($campo === 'email') type="email" @endif>@if($campo === 'documento') <small>CPF com 11 dígitos ou CNPJ com 14 dígitos.</small>@endif
@endif
</label>
@endforeach
@if(! $empresa)<label><input type="checkbox" name="confirmar_semelhante" value="1" style="width:auto"> Confirmei que esta é outra empresa, caso exista um cadastro semelhante.</label>@endif
