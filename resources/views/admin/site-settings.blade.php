@extends('layouts.panel')
@section('title',$section==='email'?'Configurações — e-mail':'Configurações — geral e identidade')
@section('content')
@include('admin.configuration.nav')
<div class="card settings-intro-card">
    <h2>{{ $section==='email'?'E-mail e notificações':'Geral, identidade e rodapé' }}</h2>
    <p>{{ $section==='email'?'Configure o transportador e envie um teste. O modo log não envia mensagens externas.':'Defina o nome, a marca, os textos do rodapé e os links sociais do site público. Alterar a URL não configura DNS ou certificado.' }}</p>
</div>

@if(auth()->user()->hasPermission('settings.manage'))
<div class="card settings-form-card">
    <form method="post" enctype="multipart/form-data" action="{{ route($section==='email'?'admin.settings.email.save':'admin.settings.general.save') }}">
        @csrf
        <input type="hidden" name="version" value="{{ $setting?->version??0 }}">

        @if($section==='general')
            <section class="settings-form-section" aria-labelledby="identity-heading">
                <div class="settings-form-heading">
                    <span class="eyebrow">IDENTIDADE DO SITE</span>
                    <h3 id="identity-heading">Nome, logo e contato</h3>
                    <p>Esses dados identificam sua empresa no site e no painel.</p>
                </div>
                <div class="site-brand-preview">
                    <img src="{{ $siteLogo??asset('assets/img/logo-painel.png') }}" alt="Prévia da logo de {{ config('app.name') }}">
                    <span>Prévia da identidade atual</span>
                </div>
                <div class="form-grid">
                    <div class="field"><label for="site-name">Nome do site</label><input id="site-name" name="name" maxlength="80" required value="{{ old('name',$setting?->name??config('app.name')) }}"></div>
                    <div class="field"><label for="site-url">URL principal HTTPS</label><input id="site-url" name="url" maxlength="255" required value="{{ old('url',$setting?->url??config('app.url')) }}"></div>
                    <div class="field"><label for="support-email">E-mail público de suporte</label><input id="support-email" name="support_email" type="email" value="{{ old('support_email',$setting?->support_email) }}"></div>
                    <div class="field"><label for="site-logo">Logo PNG/JPEG/WebP</label><input id="site-logo" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp"><small>Até 200 KiB e 1600 × 1600 px. Aparece no cabeçalho, rodapé e navegação do painel.</small></div>
                </div>
                <label class="check-line"><input type="checkbox" name="remove_logo" value="1"> Restaurar logo original</label>
                <label class="check-line"><input type="hidden" name="registration_enabled" value="0"><input type="checkbox" name="registration_enabled" value="1" @checked(old('registration_enabled',$setting?->registration_enabled??true))> Permitir novos cadastros públicos</label>
            </section>

            @php($socialLinks = $setting?->social_links ?? [])
            <section class="settings-form-section" aria-labelledby="footer-heading">
                <div class="settings-form-heading">
                    <span class="eyebrow">SITE PÚBLICO</span>
                    <h3 id="footer-heading">Rodapé e direitos autorais</h3>
                    <p>Personalize os textos no final das páginas públicas. Os campos vazios mantêm os textos padrão.</p>
                </div>
                <div class="field">
                    <label for="footer-description">Descrição da empresa</label>
                    <textarea id="footer-description" name="footer_description" maxlength="500" rows="3" placeholder="Uma frase curta sobre sua empresa">{{ old('footer_description',$setting?->footer_description??'Um lugar para seus projetos. Um painel para acompanhar cada passo.') }}</textarea>
                    <small>Vai abaixo do nome e da logo no rodapé.</small>
                </div>
                <div class="form-grid">
                    <div class="field"><label for="footer-copyright">Complemento dos direitos autorais</label><input id="footer-copyright" name="footer_copyright" maxlength="240" value="{{ old('footer_copyright',$setting?->footer_copyright??'Todos os direitos reservados.') }}"><small>Ex.: Todos os direitos reservados · CNPJ 00.000.000/0001-00</small></div>
                    <div class="field"><label for="footer-tagline">Assinatura curta do rodapé</label><input id="footer-tagline" name="footer_tagline" maxlength="180" value="{{ old('footer_tagline',$setting?->footer_tagline??'Feito para conectar suas ideias.') }}"><small>Exibida ao lado dos direitos autorais.</small></div>
                </div>
            </section>

            <section class="settings-form-section" aria-labelledby="social-links-heading">
                <div class="settings-form-heading">
                    <span class="eyebrow">CANAIS DA EMPRESA</span>
                    <h3 id="social-links-heading">Redes sociais</h3>
                    <p>Adicione os perfis públicos que quer mostrar no rodapé. Deixe em branco para ocultar; os links precisam usar HTTPS.</p>
                </div>
                <div class="form-grid">
                    @foreach(\App\Services\SiteConfiguration::SOCIAL_PLATFORMS as $key=>$label)
                        <div class="field"><label for="social-{{ $key }}">{{ $label }}</label><input id="social-{{ $key }}" type="url" name="social_links[{{ $key }}]" maxlength="255" value="{{ old('social_links.'.$key,$socialLinks[$key]??'') }}" placeholder="https://..."></div>
                    @endforeach
                </div>
            </section>
        @else
            <section class="settings-form-section">
                <div class="settings-form-heading"><span class="eyebrow">ENVIO DE E-MAIL</span><h3>Servidor e remetente</h3><p>Use credenciais de produção e teste o envio antes de depender das notificações.</p></div>
                <div class="form-grid">
                    <div class="field"><label>Modo</label><select name="mailer">@foreach(['inherit'=>'Manter configuração do servidor','log'=>'Log local (não entrega e-mail)','smtp'=>'SMTP'] as $key=>$label)<option value="{{ $key }}" @selected(old('mailer',$setting?->mailer??'inherit')===$key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="field"><label>Remetente</label><input type="email" name="mail_from_address" value="{{ old('mail_from_address',$setting?->mail_from_address??config('mail.from.address')) }}"></div>
                    <div class="field"><label>Host SMTP</label><input name="smtp_host" value="{{ old('smtp_host',$setting?->smtp_host??config('mail.mailers.smtp.host')) }}"></div>
                    <div class="field"><label>Porta</label><input type="number" min="1" max="65535" name="smtp_port" value="{{ old('smtp_port',$setting?->smtp_port??587) }}" required></div>
                    <div class="field"><label>Segurança</label><select name="smtp_scheme"><option value="smtp" @selected(old('smtp_scheme',$setting?->smtp_scheme??'smtp')==='smtp')>STARTTLS obrigatório</option><option value="smtps" @selected(old('smtp_scheme',$setting?->smtp_scheme)==='smtps')>TLS implícito</option></select></div>
                    <div class="field"><label>Usuário SMTP</label><input name="smtp_username" value="{{ old('smtp_username',$setting?->smtp_username??config('mail.mailers.smtp.username')) }}"></div>
                    <div class="field"><label>Senha SMTP — vazio mantém</label><input type="password" name="smtp_password" autocomplete="new-password" maxlength="2000" placeholder="{{ ($setting?->smtp_password||config('mail.mailers.smtp.password'))?'Senha configurada — vazio mantém':'Nenhuma senha configurada' }}"><small>Somente a senha fica oculta; host, porta, usuário e remetente continuam visíveis.</small></div>
                </div>
                <label class="check-line"><input type="checkbox" name="clear_smtp_password" value="1"> Remover senha SMTP salva</label>
            </section>
        @endif

        <div class="settings-form-actions"><button class="btn btn-primary">Salvar {{ $section==='email'?'configuração de e-mail':'identidade, rodapé e redes sociais' }}</button></div>
    </form>
</div>

@if($section==='email')
    <div class="card"><form method="post" action="{{ route('admin.settings.mail') }}">@csrf<button class="btn btn-ghost">Enviar teste para meu e-mail</button></form><p>Modo atual: {{ config('mail.default') }}. Um teste SMTP aceito não comprova entrega na caixa de entrada; mantenha o worker de notificações ativo.</p></div>
@endif
@else
    <div class="card"><p>Permissão somente de leitura.</p><p>Nome: {{ $setting?->name??config('app.name') }}</p><p>URL: {{ $setting?->url??config('app.url') }}</p>@if($section==='general')<p>Descrição do rodapé: {{ $setting?->footer_description??'Um lugar para seus projetos. Um painel para acompanhar cada passo.' }}</p><p>Direitos autorais: {{ $setting?->footer_copyright??'Todos os direitos reservados.' }}</p>@endif @if($section==='email')<p>Modo de e-mail: {{ $setting?->mailer??'inherit' }}</p>@endif</div>
@endif
@endsection
