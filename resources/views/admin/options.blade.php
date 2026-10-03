@extends('layouts.panel')
@section('title', 'Opções configuráveis')
@section('content')
<div class="card"><h2>{{ $product->name }}</h2><p class="muted">Acréscimos recorrentes seguem o ciclo do produto; instalação é cobrada uma vez. Esta seleção afeta a cobrança e o snapshot do serviço, mas ainda não configura recursos no provedor remoto.</p>@if(auth()->user()->hasPermission('products.manage'))
<form method="post" action="{{ route('admin.products.options.create',$product) }}">@csrf<div class="field"><label>Nome da opção</label><input name="name" type="text" required maxlength="100"></div><label class="check-line"><input type="checkbox" name="required" value="1" checked> Seleção obrigatória</label><br><button class="btn btn-primary btn-sm">Criar opção</button></form>
@endif
</div>@foreach($product->options as $option)<div class="card"><h3>{{ $option->name }} {{ $option->required?'(obrigatória)':'(opcional)' }}</h3><div class="table-wrap"><table><thead><tr><th>Valor</th><th>Recorrente</th><th>Instalação</th><th></th></tr></thead><tbody>@foreach($option->values as $value)<tr><td>{{ $value->label }} · {{ $value->active?'Disponível':'Desativado' }}</td><td>{{ brl($value->recurring_minor) }}</td><td>{{ brl($value->setup_minor) }}</td><td>@if(auth()->user()->hasPermission('products.manage'))
<form method="post" action="{{ route('admin.products.values.toggle',[$product,$value]) }}">@csrf<button class="btn btn-ghost btn-sm">Alternar disponibilidade</button></form>
@endif
</td></tr>@endforeach</tbody></table></div>@if(auth()->user()->hasPermission('products.manage'))
<form method="post" action="{{ route('admin.products.values.create',[$product,$option]) }}">@csrf<div class="form-grid"><div class="field"><label>Rótulo</label><input name="label" type="text" required></div><div class="field"><label>Acréscimo recorrente (R$)</label><input name="recurring" type="text" value="0,00" required></div><div class="field"><label>Acréscimo de instalação (R$)</label><input name="setup" type="text" value="0,00" required></div></div><button class="btn btn-primary btn-sm">Adicionar valor</button></form>
@endif
</div>@endforeach
@endsection
