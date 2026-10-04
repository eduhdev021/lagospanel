<?php

namespace App\Console\Commands;

use App\Models\DomainTld;
use App\Models\Product;
use App\Models\ProductAddon;
use App\Models\User;
use Illuminate\Console\Command;

class Demo extends Command
{
    protected $signature = 'lagos:demo';

    protected $description = 'Gera contas e produtos fictícios apenas no ambiente local.';

    public function handle(): int
    {
        if (! app()->environment('local', 'testing')) {
            $this->error('Permitido apenas em ambiente local.');

            return 1;
        }
        $pw = bin2hex(random_bytes(10)).'Aa1';
        foreach (['cliente@lagos.test' => false, 'admin@lagos.test' => true] as $email => $admin) {
            $u = User::firstOrNew(['email' => $email]);
            if ($u->exists) {
                $this->error('Demonstração já existe; não sobrescrevemos senhas.');

                return 1;
            }
            $u->name = $admin ? 'Administrador Demo' : 'Cliente Demo';
            $u->password = $pw;
            $u->is_admin = $admin;
            $u->email_verified_at = now();
            $u->save();
        }
        foreach ([['Hospedagem Essencial', 'essencial', 'Hospedagem', 2490, 'Um plano de demonstração para conhecer a área do cliente.'], ['Cloud Performance', 'cloud', 'Cloud', 7990, 'Plano fictício com cobrança mensal e ativação manual.'], ['Servidor de Jogos', 'jogos', 'Jogos', 4990, 'Exemplo de catálogo. Nenhum servidor real será criado.']] as [$name,$slug,$cat,$price,$desc]) {
            Product::firstOrCreate(['slug' => $slug], ['name' => $name, 'category' => $cat, 'price_minor' => $price, 'cycle' => 'monthly', 'description' => $desc]);
        }
        foreach ([['.com.br', 4490, 4490, 4990, 'registrobr'], ['.com', 6990, 6990, 7490, 'enom'], ['.net', 7490, 7490, 7990, 'enom'], ['.io', 19990, 19990, 21990, 'namecheap']] as [$tld, $reg, $tr, $ren, $registrar]) {
            DomainTld::firstOrCreate(['tld' => $tld], ['register_minor' => $reg, 'transfer_minor' => $tr, 'renew_minor' => $ren, 'registrar' => $registrar, 'active' => true]);
        }
        foreach ([['IPv4 Dedicado', 1990, 'Endereço IPv4 exclusivo vinculado ao serviço.'], ['Backup Diário Gerenciado', 1490, 'Retenção diária automatizada de 7 dias.']] as [$aname, $aprice, $adesc]) {
            ProductAddon::firstOrCreate(['name' => $aname], ['price_minor' => $aprice, 'setup_minor' => 0, 'cycle' => 'monthly', 'description' => $adesc, 'active' => true]);
        }
        $this->line(json_encode(['client' => 'cliente@lagos.test', 'admin' => 'admin@lagos.test', 'password' => $pw]));

        return 0;
    }
}
