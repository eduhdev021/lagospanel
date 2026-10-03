@extends('layouts.panel')
@section('title', 'Respostas prontas')
@section('content')
<div class="card"><div class="row-between"><h2>Biblioteca de atendimento</h2><a class="btn btn-ghost" href="{{ route('admin.tickets') }}">Voltar ao atendimento</a></div><p class="muted">Textos compartilhados pela equipe. Não armazene senhas ou dados pessoais. Inserir um modelo apenas prepara o rascunho: revise antes de enviar. Departamento é filtro de uso, não restrição de acesso da equipe.</p></div>
@if(auth()->user()->hasPermission('support.manage'))
<div class="card"><h3>{{ $editing ? 'Editar resposta pronta' : 'Nova resposta pronta' }}</h3><form method="post" action="{{ $editing ? route('admin.tickets.templates.update',$editing) : route('admin.tickets.templates.create') }}">@csrf
@if($editing)<input type="hidden" name="version" value="{{ $editing->version }}">@endif
<div class="field"><label>Título</label><input name="title" maxlength="150" value="{{ old('title',$editing?->title) }}" required></div>
<div class="field"><label>Departamento de uso</label><select name="department"><option value="">Todos</option>@foreach(['support'=>'Suporte técnico','billing'=>'Financeiro'] as $key=>$label)<option value="{{ $key }}" @selected(old('department',$editing?->department)===$key)>{{ $label }}</option>@endforeach</select></div>
<div class="field"><label>Texto da resposta</label><textarea name="body" maxlength="10000" required>{{ old('body',$editing?->body) }}</textarea></div><label><input type="checkbox" name="active" value="1" @checked(old('active',$editing?->active??true))> Disponível para inserir</label><br><button class="btn btn-primary">Salvar resposta pronta</button> <a href="{{ route('admin.tickets.templates') }}">Novo modelo</a></form></div>
@endif
<div class="card"><div class="table-wrap"><table><thead><tr><th>Título</th><th>Departamento</th><th>Estado</th><th>Ação</th></tr></thead><tbody>@forelse($templates as $template)<tr><td>{{ $template->title }}</td><td>{{ ['support'=>'Suporte técnico','billing'=>'Financeiro'][$template->department] ?? 'Todos' }}</td><td>{{ $template->active ? 'Ativo' : 'Desativado' }}</td><td><a href="{{ route('admin.tickets.templates.edit',$template) }}">{{ auth()->user()->hasPermission('support.manage') ? 'Editar' : 'Consultar' }}</a><details><summary>Texto</summary><p class="ticket-body">{{ $template->body }}</p></details></td></tr>@empty<tr><td colspan="4">Nenhuma resposta pronta cadastrada.</td></tr>@endforelse</tbody></table></div></div>{{ $templates->links('layouts.pagination') }}
@endsection
