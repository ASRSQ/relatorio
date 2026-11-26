@extends('admin.layout')
@section('content')
<h1>Turmas</h1>@if(auth()->user()->is_admin)<section class="card"><h2>Nova turma</h2><p>O professor orientador pode ser vinculado depois, no cadastro do professor ou nesta página.</p><form method="post" action="{{route('admin.turmas.salvar')}}">@csrf @include('gestao.turma-campos', ['turma'=>null])<p><button>Cadastrar turma</button></p></form></section>@endif
@foreach($turmas as $turma)<section class="card"><h2>{{$turma->nome}} — {{$turma->ano_letivo}}</h2><p>Escola: {{$turma->escola ?: 'Não informada'}} · Curso: {{$turma->curso}} · Série: {{$turma->serie ?: 'Não informada'}} · Alunos: {{$turma->alunos_count}} · Situação: {{$turma->ativa ? 'Ativa' : 'Inativa'}}</p><p>Professores: {{$turma->professores->pluck('name')->join(', ') ?: 'Nenhum — vinculação pendente'}}</p><p><a href="{{route('admin.index', ['turma_id'=>$turma->id])}}">Ver alunos e relatórios</a></p>@if(auth()->user()->is_admin)<form method="post" action="{{route('admin.turmas.professores', $turma)}}">@csrf<label>Professor<select name="professor_id" required><option value="">Selecione</option>@foreach($professores as $professor)<option value="{{$professor->id}}">{{$professor->name}}</option>@endforeach</select></label><button>Vincular professor</button></form>@endif
<form method="post" action="{{route('admin.turmas.carga', $turma)}}" class="carga-turma" data-anterior="{{$turma->carga_horaria}}" data-alunos="{{$turma->alunos_count}}">@csrf
<label>Carga hor?ria obrigat?ria (horas)<input type="number" name="carga_horaria" min="1" max="65535" inputmode="numeric" value="{{$turma->carga_horaria}}" required></label>
<input type="hidden" name="carga_anterior" value="{{$turma->carga_horaria}}"><input type="hidden" name="alunos_confirmados" value="{{$turma->alunos_count}}"><button>Alterar carga hor?ria</button></form>
@if(auth()->user()->is_admin)<details><summary>Editar turma</summary><form method="post" action="{{route('admin.turmas.salvar',$turma)}}">@csrf @include('gestao.turma-campos')<p><button>Salvar</button></p></form></details>@endif</section>@endforeach
<script>
document.querySelectorAll('.carga-turma').forEach(form => form.addEventListener('submit', event => {
    const anterior = Number(form.dataset.anterior), nova = Number(form.elements.carga_horaria.value), alunos = Number(form.dataset.alunos);
    if (anterior !== nova && !confirm(`A carga hor?ria obrigat?ria ser? alterada de ${anterior} para ${nova} horas para todos os ${alunos} alunos desta turma. Deseja continuar?`)) event.preventDefault();
}));
</script>
@endsection
