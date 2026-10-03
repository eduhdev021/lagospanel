<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Doctor extends Command
{
    protected $signature = 'lagos:doctor {--production}';

    protected $description = 'Verifica configuração e conectividade sem exibir segredos.';

    public function handle(): int
    {
        $checks = ['APP_KEY definida' => (bool) config('app.key'), 'Banco acessível' => false, 'Fila persistente' => config('queue.default') === 'database', 'Dependências instaladas' => is_file(base_path('.cache/vendor/autoload.php'))];
        try {
            DB::select('select 1');
            $checks['Banco acessível'] = true;
        } catch (\Throwable) {
        }
        if ($this->option('production')) {
            $checks += ['Ambiente production' => app()->isProduction(), 'Debug desativado' => ! config('app.debug'), 'APP_URL HTTPS' => str_starts_with(config('app.url'), 'https://'), 'Cookie seguro' => (bool) config('session.secure'), 'SMTP configurado' => config('mail.default') === 'smtp' && (bool) config('mail.mailers.smtp.host'), 'Administrador cadastrado' => User::where('is_admin', true)->exists(), 'Equipe com 2FA' => ! User::where(fn ($q) => $q->where('is_admin', true)->orWhereNotNull('staff_role_id'))->whereNull('totp_secret')->exists()];
            if (config('lagos.payments.stripe.enabled') || config('lagos.payments.mercadopago.enabled')) {
                $checks['Gateways em modo live'] = (bool) config('lagos.payments.live');
            }
        }
        foreach ($checks as $label => $ok) {
            $this->line(($ok ? 'OK  ' : 'FAIL ').$label);
        }
        $this->comment('Não substitui homologação externa, monitoramento, teste de backup ou revisão de segurança.');

        return in_array(false, $checks, true) ? 1 : 0;
    }
}
