@extends('layouts.panel')
@section('title', 'Base de conhecimento')
@section('content')
<div class="card"><h2>{{ $editing?->exists?'Editar artigo':'Novo artigo' }}</h2>@if(auth()->user()->hasPermission('knowledge.manage'))
<form method="post" action="{{ $editing?->exists?route('admin.knowledge.update',$editing):route('admin.knowledge.create') }}">@csrf<div class="form-grid">@foreach(['title'=>'Título','slug'=>'Identificador','category'=>'Categoria'] as $field=>$label)<div class="field"><label>{{ $label }}</label><input type="text" name="{{ $field }}" value="{{ old($field,$editing?->$field) }}" required></div>@endforeach</div><div class="field"><label>Conteúdo (texto, sem HTML executável)</label><textarea name="body" required maxlength="50000">{{ old('body',$editing?->body) }}</textarea></div><label class="check-line"><input type="checkbox" name="published" value="1" @checked(old('published',$editing?->published??false))> Publicar</label><br><button class="btn btn-primary">Salvar artigo</button></form>
@endif
</div>@foreach($articles as $article)<div class="card"><h3>{{ $article->title }}</h3><p>{{ $article->published?'Publicado':'Rascunho' }} · {{ $article->category }}</p><a href="{{ route('admin.knowledge.edit',$article) }}">Editar</a></div>@endforeach
{{ $articles->links('layouts.pagination') }}
@endsection
