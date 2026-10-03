<?php

namespace App\Console\Commands;

use App\Models\Product;
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
        foreach ([['Hospedagem Essencial', 'essencial', 2490, 'Um plano de demonstração para conhecer a área do cliente.'], ['Cloud Performance', 'cloud', 7990, 'Plano fictício com cobrança mensal e ativação manual.'], ['Servidor de Jogos', 'jogos', 4990, 'Exemplo de catálogo. Nenhum servidor real será criado.']] as [$name,$slug,$price,$desc]) {
            Product::firstOrCreate(['slug' => $slug], ['name' => $name, 'price_minor' => $price, 'cycle' => 'monthly', 'description' => $desc]);
        }
        $this->line(json_encode(['client' => 'cliente@lagos.test', 'admin' => 'admin@lagos.test', 'password' => $pw]));

        return 0;
    }
}
