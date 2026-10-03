@extends('layouts.public')
@section('title', 'Artigo de ajuda')
@section('content')
<article class="card"><small>{{ $article->category }}</small><h1>{{ $article->title }}</h1><p class="muted">Atualizado em {{ $article->updated_at->format('d/m/Y') }}</p><div class="preserve">{{ $article->body }}</div></article><a href="{{ route('knowledge.index') }}">← Base de conhecimento</a>
@endsection
