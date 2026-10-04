<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $subject ?? $title }}</title>
    <style>
        body{margin:0;background:#f4f6fb;color:#1d2940;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;line-height:1.6}
        .shell{width:100%;padding:28px 12px}.frame{max-width:640px;margin:0 auto}.brand{padding:22px 28px;background:#211042;border-radius:18px 18px 0 0;color:#fff}.brand-mark{display:inline-block;width:34px;height:34px;margin-right:9px;border-radius:10px;background:linear-gradient(135deg,#7c3aed,#ca65eb);vertical-align:middle}.brand-name{font-size:18px;font-weight:800;letter-spacing:-.03em;vertical-align:middle}.preheader{display:none!important;opacity:0;color:transparent;height:0;width:0;overflow:hidden}.card{padding:36px 38px;background:#fff;border:1px solid #e5e9f2;border-top:0;border-radius:0 0 18px 18px;box-shadow:0 16px 40px rgba(35,43,75,.08)}.eyebrow{color:#7c3aed;font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.title{margin:10px 0 14px;color:#172238;font-size:29px;line-height:1.2;letter-spacing:-.045em}.greeting{font-size:16px;color:#273653;margin:0 0 14px}.intro{font-size:15px;color:#66738a;margin:0 0 24px}.status{display:inline-block;margin:0 0 22px;padding:7px 12px;border-radius:999px;background:#f0e9ff;color:#6d28d9;font-size:12px;font-weight:800}.details{margin:24px 0;border:1px solid #e6eaf2;border-radius:12px;overflow:hidden}.detail{display:flex;justify-content:space-between;gap:18px;padding:13px 16px;border-bottom:1px solid #edf0f5;font-size:14px}.detail:last-child{border-bottom:0}.detail-label{color:#78849a}.detail-value{color:#1d2940;font-weight:700;text-align:right;overflow-wrap:anywhere}.button{display:inline-block;margin:10px 0 20px;padding:13px 21px;border-radius:10px;background:linear-gradient(135deg,#7c3aed,#c03de1);color:#fff!important;font-size:14px;font-weight:800;text-decoration:none;box-shadow:0 8px 18px rgba(124,58,237,.22)}.note{padding:14px 16px;border-left:3px solid #c4a5ff;background:#faf8ff;color:#68758a;font-size:13px}.footer{padding:22px 10px;text-align:center;color:#8994a7;font-size:12px}.footer a{color:#7c3aed;text-decoration:none}.small{font-size:12px;color:#8994a7}@media(max-width:520px){.shell{padding:12px 8px}.brand{padding:18px 20px}.card{padding:28px 21px}.title{font-size:25px}.detail{display:block}.detail-value{display:block;margin-top:3px;text-align:left}}
    </style>
</head>
<body>
    <div class="preheader">{{ $preheader ?? $intro ?? $title }}</div>
    <div class="shell"><div class="frame">
        <div class="brand"><span class="brand-mark"></span><span class="brand-name">{{ config('app.name', 'LagosPanel') }}</span></div>
        <div class="card">
            @isset($eyebrow)<div class="eyebrow">{{ $eyebrow }}</div>@endisset
            <h1 class="title">{{ $title }}</h1>
            @isset($greeting)<p class="greeting">{{ $greeting }}</p>@endisset
            <p class="intro">{{ $intro }}</p>
            @isset($status)<div class="status">{{ $status }}</div>@endisset
            @if(!empty($details))<div class="details">@foreach($details as $detail)<div class="detail"><span class="detail-label">{{ $detail['label'] }}</span><span class="detail-value">{{ $detail['value'] }}</span></div>@endforeach</div>@endif
            @isset($action_url)<a class="button" href="{{ $action_url }}">{{ $action_label ?? 'Abrir painel' }}</a>@endisset
            @isset($note)<div class="note">{{ $note }}</div>@endisset
            <p class="small" style="margin-top:26px">Se você não esperava esta mensagem, ignore-a ou fale com o suporte. Nunca solicitaremos sua senha por e-mail.</p>
        </div>
        <div class="footer">{{ config('app.name', 'LagosPanel') }} · <a href="{{ url('/painel') }}">Abrir painel</a><br>Mensagem automática, não responda diretamente.</div>
    </div></div>
</body>
</html>
