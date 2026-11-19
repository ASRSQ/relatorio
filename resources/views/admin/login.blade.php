@extends('admin.layout')
@section('content')
<section class="card" style="max-width:460px;margin:50px auto"><h1>Acesso do professor</h1><p>Entre com seu e-mail e senha institucional cadastrados.</p><form method="post" action="{{route('admin.entrar')}}">@csrf<label for="email">E-mail</label><input id="email" name="email" type="email" autocomplete="username" required maxlength="255" value="{{old('email')}}"><label for="password">Senha</label><input id="password" name="password" type="password" autocomplete="current-password" required maxlength="255"><p><button>Entrar no painel</button></p></form><a href="{{route('estagio.login')}}">Acesso do aluno</a></section>
@endsection
