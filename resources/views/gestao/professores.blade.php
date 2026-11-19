@extends('admin.layout')
@section('content')
<h1>Professores</h1><section class="card"><h2>Cadastrar professor</h2><form method="post" action="{{route('admin.professores.salvar')}}">@csrf
@include('gestao.professor-campos', ['professor'=>null])<p><button>Cadastrar</button></p></form></section>
@foreach($professores as $professor)<section class="card"><details><summary>{{$professor->name}} — {{$professor->ativo ? 'Ativo' : 'Inativo'}}</summary><p>Turmas: {{$professor->turmas->pluck('nome')->join(', ') ?: 'Nenhuma'}}</p><form method="post" action="{{route('admin.professores.salvar', $professor)}}">@csrf @include('gestao.professor-campos')<p><button>Salvar alterações</button></p></form></details></section>@endforeach
@endsection
