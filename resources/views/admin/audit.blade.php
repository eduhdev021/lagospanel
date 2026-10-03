@extends('layouts.panel')
@section('title', 'Auditoria')
@section('content')
<div class="card">@include('admin.event-table')</div>{{ $events->links('layouts.pagination') }}
@endsection
