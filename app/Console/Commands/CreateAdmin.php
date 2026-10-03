<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'lagos:admin {email} {--name=Administrador}';

    protected $description = 'Cria uma conta administrativa; solicita senha sem gravá-la em argumentos.';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->exists()) {
            $this->error('E-mail inválido ou já cadastrado.');

            return 1;
        }
        $password = $this->secret('Senha (mínimo 12 caracteres, letras e números)');
        if (! is_string($password) || strlen($password) < 12 || ! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password)) {
            $this->error('Senha insuficiente.');

            return 1;
        }
        $u = User::create(['name' => $this->option('name'), 'email' => $email, 'password' => $password]);
        $u->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
        $this->info('Administrador criado. Ative a autenticação em duas etapas no perfil.');

        return 0;
    }
}
