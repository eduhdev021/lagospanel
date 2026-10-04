@extends('layouts.panel')
@section('title',$section==='email'?'Configurações — e-mail':'Configurações — identidade do site')
@section('content')
@include('admin.configuration.nav')
<div class="card settings-intro-card">
    <h2>{{ $section==='email'?'E-mail e notificações':'Identidade, aparência e conteúdo público' }}</h2>
    <p>{{ $section==='email'?'Configure o transportador e envie um teste. O modo log não envia mensagens externas.':'Personalize marca, rodapé, redes sociais, cores e os metadados exibidos quando suas páginas são compartilhadas.' }}</p>
</div>

@if(auth()->user()->hasPermission('settings.manage'))
<div class="card settings-form-card">
    <form method="post" enctype="multipart/form-data" action="{{ route($section==='email'?'admin.settings.email.save':'admin.settings.general.save') }}">
        @csrf
        <input type="hidden" name="version" value="{{ $setting?->version??0 }}">

        @if($section==='general')
            @php
                $socialLinks = $setting?->social_links ?? [];
                $savedFooterLinks = $setting?->footer_links === null
                    ? \App\Services\SiteConfiguration::DEFAULT_FOOTER_LINKS
                    : \App\Services\SiteConfiguration::safeFooterLinks($setting?->footer_links);
                $postedFooterLinks = old('footer_links', $savedFooterLinks);
                $footerLinks = [];
                if (is_array($postedFooterLinks)) {
                    foreach (array_slice($postedFooterLinks, 0, 10) as $link) {
                        if (! is_array($link)) {
                            continue;
                        }
                        $footerLinks[] = [
                            'group' => in_array($link['group'] ?? null, ['explore', 'information'], true) ? $link['group'] : 'explore',
                            'label' => is_string($link['label'] ?? null) ? $link['label'] : '',
                            'url' => is_string($link['url'] ?? null) ? $link['url'] : '',
                            'order' => isset($link['order']) && is_scalar($link['order']) && filter_var($link['order'], FILTER_VALIDATE_INT) !== false ? (int) $link['order'] : count($footerLinks) + 1,
                        ];
                    }
                }
                while (count($footerLinks) < 10) {
                    $footerLinks[] = ['group' => 'explore', 'label' => '', 'url' => '', 'order' => count($footerLinks) + 1];
                }
                $brandColorInput = old('brand_color', \App\Services\SiteConfiguration::safeColor($setting?->brand_color, \App\Services\SiteConfiguration::DEFAULT_BRAND_COLOR));
                $accentColorInput = old('accent_color', \App\Services\SiteConfiguration::safeColor($setting?->accent_color, \App\Services\SiteConfiguration::DEFAULT_ACCENT_COLOR));
            @endphp

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
                    <div class="field"><label for="site-logo">Logo PNG/JPEG/WebP</label><input id="site-logo" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp"><small>Até 200 KiB e 1600 × 1600 px. Aparece no cabeçalho, rodapé e painel.</small></div>
                    <div class="field"><label for="site-favicon">Favicon PNG/WebP</label><input id="site-favicon" type="file" name="favicon" accept=".png,.webp"><small>Quadrado, até 512 × 512 px e 100 KiB. Também define o ícone de tela inicial.</small></div>
                    <div class="field"><label>Favicon atual</label><img class="settings-favicon-preview" data-image-preview="favicon" data-default-src="{{ asset('assets/img/favicon.png') }}" src="{{ $siteFavicon??asset('assets/img/favicon.png') }}" alt="Favicon atual" width="40" height="40"></div>
                </div>
                <label class="check-line"><input type="checkbox" name="remove_logo" value="1"> Restaurar logo original</label>
                <label class="check-line"><input type="checkbox" name="remove_favicon" value="1"> Restaurar favicon original</label>
                <label class="check-line"><input type="hidden" name="registration_enabled" value="0"><input type="checkbox" name="registration_enabled" value="1" @checked(old('registration_enabled',$setting?->registration_enabled??true))> Permitir novos cadastros públicos</label>
            </section>

            <section class="settings-form-section" aria-labelledby="footer-heading">
                <div class="settings-form-heading">
                    <span class="eyebrow">SITE PÚBLICO</span>
                    <h3 id="footer-heading">Rodapé e direitos autorais</h3>
                    <p>Personalize a apresentação da empresa e os textos exibidos no final das páginas.</p>
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

            <section class="settings-form-section" aria-labelledby="footer-links-heading">
                <div class="settings-form-heading">
                    <span class="eyebrow">NAVEGAÇÃO</span>
                    <h3 id="footer-links-heading">Colunas e links do rodapé</h3>
                    <p>Edite os títulos e destinos; o menor número define a ordem de exibição. Para ocultar uma linha, deixe o nome e o endereço vazios; há até 10 espaços editáveis.</p>
                </div>
                <div class="form-grid">
                    <div class="field"><label for="footer-explore-title">Título da primeira coluna</label><input id="footer-explore-title" name="footer_explore_title" maxlength="60" value="{{ old('footer_explore_title',$setting?->footer_explore_title??'Explore') }}"></div>
                    <div class="field"><label for="footer-info-title">Título da segunda coluna</label><input id="footer-info-title" name="footer_info_title" maxlength="60" value="{{ old('footer_info_title',$setting?->footer_info_title??'Informações') }}"></div>
                </div>
                <div class="footer-link-editor">
                    @foreach($footerLinks as $index=>$link)
                        <div class="footer-link-row">
                            <div class="field"><label for="footer-link-order-{{ $index }}">Ordem</label><input id="footer-link-order-{{ $index }}" type="number" min="1" max="10" name="footer_links[{{ $index }}][order]" value="{{ $link['order']??$index+1 }}"></div>
                            <div class="field"><label for="footer-link-group-{{ $index }}">Coluna</label><select id="footer-link-group-{{ $index }}" name="footer_links[{{ $index }}][group]"><option value="explore" @selected(($link['group']??'explore')==='explore')>Primeira coluna</option><option value="information" @selected(($link['group']??'explore')==='information')>Segunda coluna</option></select></div>
                            <div class="field"><label for="footer-link-label-{{ $index }}">Nome</label><input id="footer-link-label-{{ $index }}" name="footer_links[{{ $index }}][label]" maxlength="60" value="{{ $link['label']??'' }}" placeholder="Ex.: Planos"></div>
                            <div class="field"><label for="footer-link-url-{{ $index }}">Endereço</label><input id="footer-link-url-{{ $index }}" name="footer_links[{{ $index }}][url]" maxlength="255" value="{{ $link['url']??'' }}" placeholder="/loja ou https://exemplo.com"></div>
                        </div>
                    @endforeach
                </div>
                <p class="footer-link-hint">Aceitos: caminhos do próprio site começando por /, endereços HTTPS, mailto: e tel:. Links externos seguros abrem em outra aba.</p>
            </section>

            <section class="settings-form-section" aria-labelledby="social-links-heading">
                <div class="settings-form-heading">
                    <span class="eyebrow">CANAIS DA EMPRESA</span>
                    <h3 id="social-links-heading">Redes sociais</h3>
                    <p>Adicione perfis públicos para exibir ícones acessíveis no rodapé. Deixe em branco para ocultar; os links precisam usar HTTPS.</p>
                </div>
                <div class="form-grid">
                    @foreach(\App\Services\SiteConfiguration::SOCIAL_PLATFORMS as $key=>$label)
                        <div class="field"><label for="social-{{ $key }}">{{ $label }}</label><input id="social-{{ $key }}" type="url" name="social_links[{{ $key }}]" maxlength="255" value="{{ old('social_links.'.$key,$socialLinks[$key]??'') }}" placeholder="https://..."></div>
                    @endforeach
                </div>
            </section>

            <section class="settings-form-section" aria-labelledby="appearance-heading">
                <div class="settings-form-heading">
                    <span class="eyebrow">APARÊNCIA E COMPARTILHAMENTO</span>
                    <h3 id="appearance-heading">Cores da marca e SEO</h3>
                    <p>As cores principais alimentam botões, links e destaques do painel. A descrição e a imagem configuram a prévia ao compartilhar o site.</p>
                </div>
                <div class="brand-color-grid">
                    <div class="field"><label for="brand-color">Cor principal</label><input id="brand-color" type="color" name="brand_color" value="{{ $brandColorInput }}"></div>
                    <div class="field"><label for="accent-color">Cor de destaque</label><input id="accent-color" type="color" name="accent_color" value="{{ $accentColorInput }}"></div>
                </div>
                <div class="brand-color-preview" style="--preview-brand:{{ $siteBrandColor }};--preview-accent:{{ $siteAccentColor }}"><button type="button" class="preview-primary">Prévia do botão</button><span class="preview-link">Prévia de link</span></div>
                <div class="field"><label for="meta-description">Descrição para buscadores e redes</label><textarea id="meta-description" name="meta_description" maxlength="320" rows="3" placeholder="Explique em uma frase o que sua empresa oferece">{{ old('meta_description',$setting?->meta_description??$siteMetaDescription) }}</textarea><small>Até 320 caracteres. Usada em description, Open Graph e Twitter Cards.</small></div>
                <div class="field"><label for="og-image">Imagem de compartilhamento Open Graph</label><input id="og-image" type="file" name="og_image" accept=".png,.jpg,.jpeg,.webp"><small class="brand-image-note">PNG/JPEG/WebP, até 600 KiB e 2400 × 1260 px. O formato 1200 × 630 px é recomendado.</small></div>
                <div class="site-brand-preview"><img data-image-preview="og_image" data-default-src="{{ $siteLogo??asset('assets/img/logo-painel.png') }}" src="{{ $siteOpenGraphImage??$siteLogo??asset('assets/img/logo-painel.png') }}" alt="Prévia da imagem de compartilhamento"><span>Imagem social atual (ou logo padrão)</span></div>
                <label class="check-line"><input type="checkbox" name="remove_og_image" value="1"> Remover imagem de compartilhamento personalizada</label>
            </section>
        @else
            <section class="settings-form-section">
                <div class="settings-form-heading"><span class="eyebrow">ENVIO DE E-MAIL</span><h3>Servidor e remetente</h3><p>Use credenciais de produção e teste o envio antes de depender das notificações.</p></div>
                <div class="form-grid">
                    <div class="field"><label>Modo</label><select name="mailer">@foreach(['inherit'=>'Manter configuração do servidor','log'=>'Log local (não entrega e-mail)','smtp'=>'SMTP'] as $key=>$label)<option value="{{ $key }}" @selected(old('mailer',$setting?->mailer??'inherit')===$key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="field"><label>Remetente (From)</label><input type="email" name="mail_from_address" value="{{ old('mail_from_address',$setting?->mail_from_address??config('mail.from.address')) }}"><small>Recomendado: use a mesma caixa do usuário SMTP para evitar rejeição ou spoofing.</small></div>
                    <div class="field"><label>Host SMTP</label><input name="smtp_host" value="{{ old('smtp_host',$setting?->smtp_host??config('mail.mailers.smtp.host')) }}"></div>
                    <div class="field"><label>Porta</label><input type="number" min="1" max="65535" name="smtp_port" value="{{ old('smtp_port',$setting?->smtp_port??587) }}" required></div>
                    <div class="field"><label>Segurança</label><select name="smtp_scheme"><option value="smtp" @selected(old('smtp_scheme',$setting?->smtp_scheme??'smtp')==='smtp')>STARTTLS obrigatório — portas 587/25</option><option value="smtps" @selected(old('smtp_scheme',$setting?->smtp_scheme)==='smtps')>TLS implícito — porta 465</option></select><small>Não use “TLS implícito” na porta 587.</small></div>
                    <div class="field"><label>Usuário SMTP</label><input name="smtp_username" value="{{ old('smtp_username',$setting?->smtp_username??config('mail.mailers.smtp.username')) }}"></div>
                    <div class="field"><label>Senha SMTP — vazio mantém</label><input type="password" name="smtp_password" autocomplete="new-password" maxlength="2000" placeholder="{{ ($setting?->smtp_password||config('mail.mailers.smtp.password'))?'Senha configurada — vazio mantém':'Nenhuma senha configurada' }}"><small>A senha nunca é exibida. Deixe vazio para testar usando a senha já salva.</small></div>
                </div>
                @if($setting?->mail_from_address && $setting?->smtp_username && strtolower($setting->mail_from_address)!==strtolower($setting->smtp_username))<div class="settings-callout"><strong>Remetente diferente do usuário SMTP.</strong><p>{{ $setting->mail_from_address }} será usado como From, mas a autenticação usa {{ $setting->smtp_username }}. Muitos provedores aceitam apenas a caixa autenticada; ajuste os dois para o mesmo endereço se o envio falhar.</p></div>@endif
                <label class="check-line"><input type="checkbox" name="clear_smtp_password" value="1"> Remover senha SMTP salva</label>
            </section>
        @endif

        <div class="settings-form-actions"><button class="btn btn-primary" type="submit">Salvar {{ $section==='email'?'configuração de e-mail':'identidade do site' }}</button>@if($section==='email')<button class="btn btn-ghost" type="submit" formaction="{{ route('admin.settings.mail.connection') }}" formmethod="post">Testar conexão SMTP</button>@endif</div>
    </form>
</div>

@if($section==='email')
    @if(session('mail_diagnostics'))
        @php($diagnostics = session('mail_diagnostics'))
        <div class="card mail-diagnostics" aria-live="polite"><div class="settings-form-heading"><span class="eyebrow">DIAGNÓSTICO SMTP</span><h3>{{ $diagnostics['ok'] ? 'Conexão aprovada' : 'Conexão não aprovada' }}</h3><p>{{ $diagnostics['summary'] }} {{ $diagnostics['host'] }}:{{ $diagnostics['port'] }} · {{ $diagnostics['scheme']==='smtps'?'TLS implícito':'STARTTLS' }} · {{ $diagnostics['duration_ms'] }} ms</p></div><ol class="mail-check-list">@foreach($diagnostics['steps'] as $step)<li class="mail-check {{ $step['ok']?'is-ok':'is-failed' }}"><strong>{{ $step['ok']?'✓':'×' }} {{ $step['label'] }}</strong><span>{{ $step['message'] }}</span>@if(!empty($step['meta']))<code>{{ collect($step['meta'])->map(fn($value,$key) => $key.'='.$value)->implode(' · ') }}</code>@endif</li>@endforeach</ol><p class="muted">Este botão não envia e-mail. Ele valida DNS, conexão TCP, negociação TLS, capacidades SMTP e autenticação. Use o botão abaixo para confirmar o envio.</p></div>
    @endif
    <div class="card"><form method="post" action="{{ route('admin.settings.mail') }}">@csrf<button class="btn btn-ghost">Enviar teste para meu e-mail</button></form><p>O envio usa a configuração atualmente carregada pelo painel. O resultado mostra se o servidor aceitou a mensagem e o identificador de correlação; aceitação SMTP não garante que o Gmail/Outlook colocou a mensagem na caixa de entrada.</p></div>
@endif
@else
    <div class="card"><p>Permissão somente de leitura.</p><p>Nome: {{ $setting?->name??config('app.name') }}</p><p>URL: {{ $setting?->url??config('app.url') }}</p>@if($section==='general')<p>Descrição do rodapé: {{ $siteFooterDescription }}</p><p>Direitos autorais: {{ $siteFooterCopyright }}</p><p>Links no rodapé: {{ count($siteFooterLinks) }}</p><p>Cor principal: {{ $siteBrandColor }} · Destaque: {{ $siteAccentColor }}</p><p>Descrição SEO: {{ $siteMetaDescription }}</p>@endif @if($section==='email')<p>Modo de e-mail: {{ $setting?->mailer??'inherit' }}</p>@endif</div>
@endif
@if($section==='general')<script src="{{ panel_asset('assets/identity-settings.js') }}" defer></script>@endif
@endsection
