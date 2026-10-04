<?php

namespace Tests\Feature;

use App\Models\AdminPreference;
use App\Models\PanelUpdate;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function update(): PanelUpdate
    {
        return PanelUpdate::create(['user_id' => $this->admin()->id, 'source_sha' => str_repeat('a', 40), 'target_sha' => str_repeat('b', 40), 'status' => 'checked', 'phase' => 'checked']);
    }

    public function test_production_admin_access_is_optional_by_default(): void
    {
        $this->app['env'] = 'production';
        $this->actingAs($this->admin())->get(route('admin.settings.index'))->assertOk();
    }

    public function test_settings_pages_render(): void
    {
        $this->actingAs($this->admin());
        foreach (['index', 'general', 'email', 'security', 'environment', 'updates'] as $page) {
            $this->get(route('admin.settings.'.$page))->assertOk();
        }
    }

    public function test_client_cannot_access_settings(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_security_requires_password_and_saves_optional_policy(): void
    {
        $this->actingAs($this->admin());
        $data = ['version' => 0, 'ack' => 1, 'require_two_factor' => 0, 'password' => 'incorrect'];
        $this->post(route('admin.settings.security.save'), $data)->assertSessionHasErrors('password');
        $data['password'] = 'password';
        $this->post(route('admin.settings.security.save'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(AdminPreference::find(1)->require_two_factor);
        $this->post(route('admin.settings.security.save'), $data)->assertStatus(409);
    }

    public function test_cannot_require_two_factor_without_enrollment(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.security.save'), ['version' => 0, 'ack' => 1, 'require_two_factor' => 1, 'password' => 'password'])->assertSessionHasErrors('require_two_factor');
    }

    public function test_general_save_does_not_require_or_replace_smtp(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.settings.general.save'), ['version' => 0, 'name' => 'Minha hospedagem', 'url' => 'https://example.com', 'smtp_password' => 'ignored'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Minha hospedagem', SiteSetting::find(1)->name);
        $this->assertNull(SiteSetting::find(1)->smtp_password);
    }

    public function test_update_page_handles_checked_record(): void
    {
        $this->update();
        $this->actingAs($this->admin())->get(route('admin.settings.updates'))->assertOk()->assertSee('Ver alterações no GitHub');
    }

    public function test_update_requires_enabled_worker(): void
    {
        $u = $this->update();
        $this->actingAs($this->admin())->post(route('admin.settings.updates.approve', $u), ['password' => 'password', 'ack' => 1])->assertStatus(409);
        $this->assertSame('checked', $u->fresh()->status);
    }

    public function test_approval_and_cancellation_do_not_change_source(): void
    {
        config(['panel_updates.enabled' => true]);
        AdminPreference::ensure();
        AdminPreference::find(1)->update(['updater_heartbeat_at' => now()]);
        $u = $this->update();
        $actor = $this->admin();
        $this->actingAs($actor)->post(route('admin.settings.updates.approve', $u), ['password' => 'password', 'ack' => 1])->assertRedirect();
        $this->assertSame('pending', $u->fresh()->status);
        $this->assertSame($actor->id, $u->fresh()->user_id);
        $this->get(route('admin.settings.updates.state', $u))->assertOk()->assertJsonMissingPath('backup_path');
        $this->post(route('admin.settings.updates.cancel', $u))->assertRedirect();
        $this->assertSame('cancelled', $u->fresh()->status);
    }

    public function test_expired_check_is_rejected(): void
    {
        config(['panel_updates.enabled' => true]);
        AdminPreference::ensure();
        AdminPreference::find(1)->update(['updater_heartbeat_at' => now()]);
        $u = $this->update();
        $u->update(['created_at' => now()->subMinutes(16)]);
        $this->actingAs($this->admin())->post(route('admin.settings.updates.approve', $u), ['password' => 'password', 'ack' => 1])->assertStatus(409);
    }
}
