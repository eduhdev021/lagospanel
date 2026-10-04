<?php

use App\Models\Product;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || config('database.connections.sqlite.database') !== base_path('.cache/experience.sqlite')) {
    exit(1);
}
Artisan::call('migrate', ['--force' => true]);
$pw = 'DemoOnly-Experience-4928';
foreach (['admin' => true, 'client' => false] as $name => $admin) {
    $u = User::firstOrNew(['email' => $name.'@experience.invalid']);
    $u->forceFill(['name' => $admin ? 'Administrador de demonstração' : 'Cliente de demonstração', 'password' => $pw, 'is_admin' => $admin, 'email_verified_at' => now()])->save();
}
foreach (SocialProvider::LABELS as $key => $label) {
    SocialProvider::updateOrCreate(['provider' => $key], ['client_id' => 'demo-client', 'client_secret' => 'demo-secret-not-real', 'enabled' => true]);
}
foreach ([['Hospedagem Essencial', 1990, 'Um espaço para começar seu site. Exemplo visual de catálogo.'], ['Hospedagem Profissional', 3990, 'Para quem está dando o próximo passo. Plano de demonstração.'], ['Servidor para projetos', 7990, 'Explore mais possibilidades para sua aplicação. Demonstração.']] as [$name,$price,$description]) {
    Product::firstOrCreate(['name' => $name], ['slug' => Str::slug($name), 'price_minor' => $price, 'description' => $description, 'cycle' => 'monthly', 'active' => true, 'stock' => null, 'setup_minor' => 0]);
}
echo 'Local browser fixture ready. No real provider credentials.';
