@foreach(['nome'=>'Nome da empresa *','nome_fantasia'=>'Nome fantasia (opcional)','documento'=>'CPF ou CNPJ (opcional)','endereco'=>'Endereço (opcional)','numero'=>'Número (opcional)','bairro'=>'Bairro (opcional)','cidade'=>'Cidade (opcional)','estado'=>'Estado / UF (opcional)','telefone'=>'Telefone (opcional)','email'=>'E-mail (opcional)','responsavel'=>'Responsável (opcional)'] as $campo=>$rotulo)
<label>{{$rotulo}}<input name="{{$campo}}" value="{{old($campo, $empresa->$campo ?? '')}}" @required($campo === 'nome') @if($campo === 'email') type="email" @endif></label>
@endforeach
@if(! $empresa)<label><input type="checkbox" name="confirmar_semelhante" value="1" style="width:auto"> Confirmei que esta é outra empresa, caso exista um cadastro semelhante.</label>@endif
