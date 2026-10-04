@if(!auth()->user()->isStaff())
<section class="card"><h3>Contas conectadas</h3><p class="muted">Use seus provedores preferidos para entrar. Sua senha e o autenticador continuam funcionando.</p>
@foreach(\App\Models\SocialProvider::LABELS as $provider=>$label)
@php $linked=$socialIdentities->firstWhere('provider',$provider);$enabled=$socialProviders->contains('provider',$provider); @endphp
@if($linked||$enabled)<details class="connected-provider"><summary><span><img src="{{ asset('assets/brands/'.$provider.'.svg') }}" alt="" width="20" height="20">{{ $label }}</span><span class="status-pill">{{ $linked?'Conectado':'Disponível' }}</span></summary><form method="post" action="{{ route($linked?'social.unlink':'social.link',$provider) }}">@csrf @if($linked)@method('DELETE')@endif @include('admin.webhook-confirm')<button class="btn {{ $linked?'btn-ghost':'btn-primary' }}">{{ $linked?'Desconectar conta':'Conectar '.$label }}</button></form></details>@endif
@endforeach
@if($socialProviders->isEmpty()&&$socialIdentities->isEmpty())<p>Nenhum provedor foi habilitado pela administração.</p>@endif
</section>
@endif
