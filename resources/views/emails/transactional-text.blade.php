{{ config('app.name', 'LagosPanel') }}

{{ $title }}

@if(isset($greeting)){{ $greeting }}

@endif{{ $intro }}
@if(isset($status))
Status: {{ $status }}
@endif
@if(!empty($details))
@foreach($details as $detail){{ $detail['label'] }}: {{ $detail['value'] }}
@endforeach
@endif
@if(isset($action_url))
{{ $action_label ?? 'Abrir painel' }}: {{ $action_url }}
@endif
@if(isset($note))
{{ $note }}
@endif

Se você não esperava esta mensagem, ignore-a ou fale com o suporte. Nunca solicitaremos sua senha por e-mail.
