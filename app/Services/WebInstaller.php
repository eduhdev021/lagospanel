<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class WebInstaller
{
    public function state(): ?array
    {
        if (is_file(config('setup.lock_path')) || ! is_file(config('setup.state_path'))) {
            return null;
        }
        $s = json_decode(file_get_contents(config('setup.state_path')), true);

        return is_array($s) && ($s['expires_at'] ?? 0) > time() && isset($s['hash']) ? $s : null;
    }

    public function prepare(): string
    {
        $dir = dirname(config('setup.state_path'));
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $lock = fopen(config('setup.mutex_path'), 'c');
        flock($lock, LOCK_EX);
        try {
            if (is_file(config('setup.lock_path'))) {
                throw new \RuntimeException('Instalação já concluída.');
            }
            // Never arm a public first-admin installer on an existing or unreachable database.
            $driver = config('database.default');
            $path = config('database.connections.sqlite.database');
            $freshSqlite = $driver === 'sqlite' && $path !== ':memory:' && ! (DB::connection()->getRawPdo() instanceof \PDO) && ! is_file(str_starts_with($path, '/') ? $path : base_path($path));
            if (! $freshSqlite) {
                if (Schema::hasTable('users') && DB::table('users')->exists()) {
                    throw new \RuntimeException('Há usuários no banco. Use atualização normal, não o instalador.');
                }
            }
            $env = config('setup.env_path');
            if (! is_file($env)) {
                copy(base_path('.env.example'), $env);
            }
            $existing = Dotenv::parse(file_get_contents($env));
            $key = ! empty($existing['APP_KEY']) ? $existing['APP_KEY'] : (config('app.key') ?: 'base64:'.base64_encode(random_bytes(32)));
            EnvironmentFile::write($env, EnvironmentFile::replace(file_get_contents($env), ['APP_KEY' => $key, 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file']));
            $token = bin2hex(random_bytes(32));
            $previous = is_file(config('setup.state_path')) ? json_decode(file_get_contents(config('setup.state_path')), true) : [];
            $record = ['hash' => hash('sha256', $token), 'expires_at' => time() + 1800];
            if (is_string($previous['database_fingerprint'] ?? null)) {
                $record['database_fingerprint'] = $previous['database_fingerprint'];
            }
            EnvironmentFile::write(config('setup.state_path'), json_encode($record, JSON_THROW_ON_ERROR));
            Artisan::call('config:clear');

            return $token;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function requirements(): array
    {
        $checks = ['PHP 8.2 ou superior' => version_compare(PHP_VERSION, '8.2', '>='), 'Arquivo .env gravável' => is_writable(config('setup.env_path')), 'Diretório do projeto gravável' => is_writable(base_path()), 'Storage gravável' => is_writable(storage_path()), 'Banco SQLite ou MySQL disponível' => count(array_intersect(['sqlite', 'mysql'], \PDO::getAvailableDrivers())) > 0];
        foreach (['curl', 'fileinfo', 'mbstring', 'dom', 'openssl'] as $ext) {
            $checks['Extensão '.$ext] = extension_loaded($ext);
        }

        return $checks;
    }

    public function finish(array $v, string $grant): void
    {
        $lock = fopen(config('setup.mutex_path'), 'c');
        flock($lock, LOCK_EX);
        $envPath = config('setup.env_path');
        $oldEnv = file_get_contents($envPath);
        $envWritten = false;
        $committed = false;
        try {
            $state = $this->state();
            abort_unless($state && hash_equals($state['hash'], $grant), 403);
            foreach ($this->requirements() as $ok) {
                abort_unless($ok, 422, 'Pré-requisitos não atendidos.');
            }
            $driver = $v['db_driver'];
            abort_unless(in_array($driver, \PDO::getAvailableDrivers(), true), 422, 'Driver de banco indisponível.');
            $dbValues = ['DB_CONNECTION' => $driver, 'DB_DATABASE' => $driver === 'sqlite' ? config('setup.sqlite_path') : $v['db_database'], 'DB_HOST' => $driver === 'mysql' ? $v['db_host'] : '127.0.0.1', 'DB_PORT' => $driver === 'mysql' ? $v['db_port'] : '3306', 'DB_USERNAME' => $driver === 'mysql' ? $v['db_username'] : '', 'DB_PASSWORD' => $driver === 'mysql' ? ($v['db_password'] ?? '') : '', 'DB_URL' => '', 'APP_NAME' => $v['site_name'], 'APP_URL' => $v['url'], 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database', 'QUEUE_CONNECTION' => 'database'];
            $newEnv = EnvironmentFile::replace($oldEnv, $dbValues);
            if ($driver === 'sqlite') {
                $path = config('setup.sqlite_path');
                if (! is_file($path)) {
                    touch($path);
                    chmod($path, 0600);
                }config(['database.connections.sqlite.database' => $path]);
            } else {
                config(['database.connections.mysql.host' => $v['db_host'], 'database.connections.mysql.port' => $v['db_port'], 'database.connections.mysql.database' => $v['db_database'], 'database.connections.mysql.username' => $v['db_username'], 'database.connections.mysql.password' => $v['db_password'] ?? '', 'database.connections.mysql.url' => null]);
            }
            config(['database.default' => $driver]);
            DB::purge($driver);
            $fingerprint = hash('sha256', json_encode(array_diff_key($dbValues, ['DB_PASSWORD' => 1, 'APP_NAME' => 1, 'APP_URL' => 1])));
            if (Schema::hasTable('users') && DB::table('users')->exists()) {
                throw new \RuntimeException('O banco já possui usuários.');
            }
            $tables = Schema::getTables();
            if ($tables && ($state['database_fingerprint'] ?? '') !== $fingerprint) {
                throw new \RuntimeException('Use banco vazio e exclusivo.');
            }
            $state['database_fingerprint'] = $fingerprint;
            EnvironmentFile::write(config('setup.state_path'), json_encode($state, JSON_THROW_ON_ERROR));
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new \RuntimeException('Migração não concluída.');
            }
            DB::transaction(function () use ($v, $newEnv, $envPath, &$envWritten) {
                if (User::exists()) {
                    throw new \RuntimeException('Administrador já cadastrado.');
                }
                $u = User::create(['name' => $v['admin_name'], 'email' => strtolower($v['admin_email']), 'password' => $v['password']]);
                $u->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();
                SiteSetting::create(['id' => 1, 'name' => $v['site_name'], 'url' => $v['url'], 'support_email' => $v['admin_email'], 'registration_enabled' => true]);
                Audit::record('installation.completed', 'site:1', [], $u->id);
                EnvironmentFile::write($envPath, $newEnv);
                $envWritten = true;
            });
            $committed = true;
            EnvironmentFile::write(config('setup.lock_path'), json_encode(['installed_at' => gmdate('c')], JSON_THROW_ON_ERROR));
            @unlink(config('setup.state_path'));
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
        } catch (\Throwable $e) {
            if ($envWritten && ! $committed) {
                EnvironmentFile::write($envPath, $oldEnv);
            }
            // No raw DB credentials, DSN, migration output or administrator password in UI/logs.
            throw new \RuntimeException($committed ? 'Banco e administrador criados. Finalização do arquivo de bloqueio requer revisão do operador.' : 'Não foi possível instalar. Confira requisitos, banco vazio, credenciais e permissões. O instalador não apaga bancos existentes.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
