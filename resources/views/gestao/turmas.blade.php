@extends('admin.layout')
@section('content')
<h1>Turmas</h1>@if(auth()->user()->is_admin)<section class="card"><h2>Nova turma</h2><form method="post" action="{{route('admin.turmas.salvar')}}">@csrf @include('gestao.turma-campos', ['turma'=>null])<p><button>Cadastrar turma</button></p></form></section>@endif
@foreach($turmas as $turma)<section class="card"><h2>{{$turma->nome}} — {{$turma->ano_letivo}}</h2><p>{{$turma->curso}} · Professores: {{$turma->professores->pluck('name')->join(', ') ?: 'Nenhum'}}</p>@if(auth()->user()->is_admin)<details><summary>Editar turma</summary><form method="post" action="{{route('admin.turmas.salvar',$turma)}}">@csrf @include('gestao.turma-campos')<p><button>Salvar</button></p></form></details>@endif</section>@endforeach
@endsection
