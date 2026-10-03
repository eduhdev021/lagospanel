<?php

use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../.cache/vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (getenv('LAGOS_TEST_MODE') !== '1' || ! app()->environment('local')) {
    exit(1);
}
$data = [];
foreach (['writer' => ['support.view', 'support.manage'], 'viewer' => ['support.view'], 'outsider' => []] as $type => $permissions) {
    $password = bin2hex(random_bytes(12)).'Aa1';
    $u = User::create(['name' => 'Suporte '.$type, 'email' => $type.'-'.bin2hex(random_bytes(5)).'@example.test', 'password' => $password]);
    $u->forceFill(['email_verified_at' => now()]);
    if ($permissions) {
        $role = StaffRole::create(['name' => 'Support '.$u->id, 'permissions' => $permissions]);
        $u->staff_role_id = $role->id;
    }
    $u->save();
    $data[$type] = ['id' => $u->id, 'email' => $u->email, 'password' => $password];
}
echo json_encode($data);
