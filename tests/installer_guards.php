<?php

use App\Models\User;
use App\Services\WebInstaller;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Real SQLite tests. Refuses the source tree and any application outside scratch storage.
$root = realpath(getenv('LAGOS_SETUP_TEST_ROOT') ?: '');
if (getenv('LAGOS_TEST_MODE') !== '1' || ! $root || ! str_contains($root, '/.cache/') || $root === dirname(__DIR__)) {
    throw new RuntimeException('Isolated installation required.');
}
require $root.'/.cache/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local')) {
    throw new RuntimeException('Local fixture only.');
}
$dir = $root.'/.cache/installer-guards-'.bin2hex(random_bytes(5));
mkdir($dir, 0700, true);
$env = $dir.'/.env';
file_put_contents($env, 'APP_KEY='.config('app.key')."\n");
config(['setup.env_path' => $env, 'setup.state_path' => $dir.'/state', 'setup.lock_path' => $dir.'/lock', 'setup.mutex_path' => $dir.'/mutex']);
$service = app(WebInstaller::class);
$results = [];
function checkGuard(string $name, bool $ok): void
{
    global $results;
    $results[] = ['test' => $name, 'passed' => $ok];
    if (! $ok) {
        throw new RuntimeException($name);
    }
}
$before = hash_file('sha256', $env);
$rejected = false;
try {
    $service->prepare();
} catch (RuntimeException) {
    $rejected = true;
}
checkGuard('Existing real administrator blocks preparation without changing .env', $rejected && hash_file('sha256', $env) === $before && ! file_exists(config('setup.state_path')));
$data = ['db_driver' => 'sqlite', 'site_name' => 'Installer guard', 'url' => 'https://panel.example.test', 'admin_name' => 'Guard', 'admin_email' => 'guard@example.test', 'password' => bin2hex(random_bytes(16)).'Aa1'];
$unknown = $dir.'/unknown.sqlite';
$pdo = new PDO('sqlite:'.$unknown);
$pdo->exec("CREATE TABLE unrelated(value TEXT); INSERT INTO unrelated VALUES ('preserve-me')");
config(['setup.sqlite_path' => $unknown]);
file_put_contents(config('setup.state_path'), json_encode(['hash' => hash('sha256', 'guard-key'), 'expires_at' => time() + 300]));
$rejected = false;
try {
    $service->finish($data, hash('sha256', 'guard-key'));
} catch (RuntimeException) {
    $rejected = true;
}
checkGuard('Unknown nonempty database is not migrated or deleted', $rejected && $pdo->query('SELECT value FROM unrelated')->fetchColumn() === 'preserve-me' && hash_file('sha256', $env) === $before && count($pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll()) === 1);
$partial = $dir.'/partial.sqlite';
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $partial, 'setup.sqlite_path' => $partial]);
DB::purge('sqlite');
$key = $service->prepare();
$before = hash_file('sha256', $env);
$badMigration = $root.'/database/migrations/2099_01_01_000000_installer_test_failure.php';
file_put_contents($badMigration, '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration {public function up():void{throw new \\RuntimeException("Injected migration failure");}};');
register_shutdown_function(static function () use ($badMigration) {
    if (is_file($badMigration)) {
        unlink($badMigration);
    }
});
$rejected = false;
try {
    $service->finish($data, hash('sha256', $key));
} catch (RuntimeException) {
    $rejected = true;
}unlink($badMigration);
$state = $service->state();
checkGuard('Migration failure retains own fingerprint, zero users and original environment', $rejected && isset($state['database_fingerprint']) && ! User::exists() && hash_file('sha256', $env) === $before && DB::table('migrations')->count() === 13);
$state['expires_at'] = time() - 1;
file_put_contents(config('setup.state_path'), json_encode($state));
$key = $service->prepare();
checkGuard('Rearming expired partial installation preserves database identity', $service->state()['database_fingerprint'] === $state['database_fingerprint']);
$service->finish($data, hash('sha256', $key));
checkGuard('Retry completes partial real migrations with one administrator', User::count() === 1 && User::first()->is_admin && is_file(config('setup.lock_path')) && ! is_file(config('setup.state_path')));
$before = hash_file('sha256', $env);
$rejected = false;
try {
    $service->finish($data, hash('sha256', $key));
} catch (RuntimeException) {
    $rejected = true;
}
checkGuard('Duplicate finish cannot overwrite environment or create another administrator', $rejected && hash_file('sha256', $env) === $before && User::count() === 1);
echo json_encode(['results' => $results, 'database' => 'real SQLite, isolated installation', 'MySQL' => 'not homologated'],JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
