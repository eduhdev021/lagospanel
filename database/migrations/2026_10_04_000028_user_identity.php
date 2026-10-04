<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 32)->nullable()->unique()->after('name');
        });

        $used = [];
        DB::table('users')->orderBy('id')->get(['id', 'name', 'email'])->each(function (object $user) use (&$used): void {
            $base = Str::lower(Str::slug(Str::before((string) $user->email, '@')));
            if ($base === '') {
                $base = Str::lower(Str::slug((string) $user->name));
            }
            $base = substr(preg_replace('/[^a-z0-9]+/', '-', $base) ?: 'user', 0, 24);
            $base = trim($base, '-');
            if ($base === '') {
                $base = 'user';
            }

            $username = $base;
            $suffix = 2;
            while (isset($used[$username])) {
                $username = substr($base, 0, 31 - strlen((string) $suffix)).'-'.$suffix++;
            }
            $used[$username] = true;
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
