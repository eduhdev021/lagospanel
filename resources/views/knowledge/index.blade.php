@extends('layouts.public')
@section('title', 'Base de conhecimento')
@section('content')
<div class="section-head"><h1>Como podemos ajudar?</h1><p>Guias e respostas publicados pela equipe.</p></div><form method="get" action="{{ route('knowledge.index') }}" class="card"><div class="field"><label>Pesquisar</label><input name="q" type="text" value="{{ $search }}" maxlength="100"></div><button class="btn btn-primary btn-sm">Buscar</button></form>@forelse($articles as $article)<article class="card"><small>{{ $article->category }}</small><h2><a href="{{ route('knowledge.show',$article->slug) }}">{{ $article->title }}</a></h2><p>{{ Str::limit($article->body,160) }}</p></article>@empty<div class="card">Nenhum artigo encontrado.</div>@endforelse
{{ $articles->links('layouts.pagination') }}
@endsection
