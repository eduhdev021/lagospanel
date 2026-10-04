@if(($socialProviders??collect())->isNotEmpty())
<div class="social-divider"><span>ou continue com</span></div>
<div class="social-buttons">
@foreach($socialProviders as $provider)
<a class="social-button" href="{{ route('social.start',$provider->provider) }}"><img src="{{ asset('assets/brands/'.$provider->provider.'.svg') }}" alt="" width="20" height="20">{{ \App\Models\SocialProvider::LABELS[$provider->provider] }}</a>
@endforeach
</div><p class="social-note">Login social exclusivo para clientes. Sua verificação em duas etapas continua ativa.</p>
@endif
