<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\OperationalSetting;
use App\Models\Service;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\Maintenance;
use App\Services\Reminders;
use App\Services\SiteConfiguration;
use App\Services\SupportDesk;
use App\Support\OperationalSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OperationalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function root(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function values(string $section, array $overrides = []): array
    {
        $values = [];
        foreach (OperationalSettings::SECTIONS[$section]['fields'] as $name => $field) {
            $values[$name] = $field[2] === 'secret' ? '' : config($field[1]);
        }

        return array_replace($values, $overrides);
    }

    private function save(string $section, array $values = [], int $version = 0)
    {
        return $this->post(route('admin.settings.operation.save', $section), ['version' => $version, 'ack' => 1, 'password' => 'password', 'values' => $this->values($section, $values)]);
    }

    public function test_all_operational_pages_and_coverage_render(): void
    {
        $this->actingAs($this->root());
        foreach (array_keys(OperationalSettings::SECTIONS) as $section) {
            $this->get(route('admin.settings.operation', $section))->assertOk();
        }$this->get(route('admin.settings.coverage'))->assertOk()->assertSee('múltiplas moedas');
    }

    public function test_customer_and_non_root_staff_cannot_change_payments(): void
    {
        $u = User::factory()->create();
        $role = StaffRole::create(['name' => 'Operador', 'permissions' => ['settings.view', 'settings.manage']]);
        $u->forceFill(['staff_role_id' => $role->id])->save();
        $this->actingAs($u)->get(route('admin.settings.operation', 'payments'))->assertForbidden();
        $this->save('payments')->assertForbidden();
    }

    public function test_password_and_ack_required(): void
    {
        $this->actingAs($this->root());
        $this->post(route('admin.settings.operation.save', 'billing'), ['version' => 0, 'values' => $this->values('billing'), 'ack' => 1, 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post(route('admin.settings.operation.save', 'billing'), ['version' => 0, 'values' => $this->values('billing'), 'password' => 'password'])->assertSessionHasErrors('ack');
    }

    public function test_version_conflicts_do_not_overwrite(): void
    {
        $this->actingAs($this->root());
        $this->save('billing', ['issuer_name' => 'Empresa nova'])->assertRedirect()->assertSessionHasNoErrors();
        $this->save('billing', ['issuer_name' => 'Outra'])->assertStatus(409);
        $this->assertSame('Empresa nova', OperationalSetting::find('billing')->values['issuer_name']);
    }

    public function test_secrets_are_encrypted_hidden_and_blank_is_preserved(): void
    {
        $this->actingAs($this->root());
        $this->save('payments', ['stripe_secret' => 'private-stripe-secret', 'stripe_webhook' => 'private-stripe-webhook', 'stripe_enabled' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $raw = DB::table('operational_settings')->value('values');
        $this->assertStringNotContainsString('private-stripe-secret', $raw);
        $this->get(route('admin.settings.operation', 'payments'))->assertOk()->assertDontSee('private-stripe-secret');
        $this->save('payments', [], 1)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('private-stripe-secret', OperationalSetting::find('payments')->values['stripe_secret']);
    }

    public function test_failed_validation_does_not_flash_secrets(): void
    {
        $this->actingAs($this->root());
        $this->save('payments', ['stripe_secret' => 'private-stripe-secret', 'stripe_enabled' => 1])->assertSessionHasErrors('values.stripe_webhook');
        $this->assertNull(session('_old_input.values.stripe_secret'));
        $this->assertSame(1, session('_old_input.values.stripe_enabled'));
        $this->assertStringNotContainsString('private-stripe-secret', json_encode(session('_old_input')));
    }

    public function test_unknown_settings_and_invalid_ranges_are_rejected(): void
    {
        $this->actingAs($this->root());
        $this->save('billing', ['reservation_minutes' => 1])->assertSessionHasErrors('values.reservation_minutes');
        $this->save('resources', ['unknown_key' => 'unsafe'])->assertSessionHasErrors('values');
        $this->get(route('admin.settings.operation', 'arbitrary'))->assertNotFound();
    }

    public function test_sla_ordering_enforced(): void
    {
        $this->actingAs($this->root());
        $this->save('support', ['sla_urgent' => 700])->assertSessionHasErrors('values.sla_urgent');
    }

    public function test_changes_reach_existing_consumers_and_reset_in_long_lived_service(): void
    {
        $loader = app(SiteConfiguration::class);
        $original = config('documents.issuer_name');
        OperationalSetting::create(['section' => 'billing', 'values' => ['issuer_name' => 'Novo emissor', 'reservation_minutes' => 90]]);
        $loader->apply();
        $this->assertSame('Novo emissor', config('documents.issuer_name'));
        $this->assertSame(90, config('lagos.reservation_minutes'));
        OperationalSetting::whereKey('billing')->delete();
        $loader->apply();
        $this->assertSame($original, config('documents.issuer_name'));
    }

    public function test_configured_sla_applies_to_new_ticket(): void
    {
        $u = User::factory()->create();
        OperationalSetting::create(['section' => 'support', 'values' => ['sla_normal' => 12, 'attachment_mib' => 20]]);
        app(SiteConfiguration::class)->apply();
        $ticket = app(SupportDesk::class)->open($u, ['subject' => 'Ajuda', 'body' => 'Teste', 'department' => 'support']);
        $this->assertSame(12, $ticket->sla_hours);
        $this->assertSame(20 * 1048576, config('support.account_attachment_bytes'));
    }

    public function test_renewal_lead_time_and_off_switch_change_maintenance(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        Service::create(['user_id' => $u->id, 'name' => 'Serviço', 'status' => 'active', 'cycle' => 'monthly', 'price_minor' => 1000, 'next_due' => today()->addDays(10)]);
        $setting = OperationalSetting::create(['section' => 'automation', 'values' => ['renewal_days' => 15, 'renewals_enabled' => false]]);
        app(SiteConfiguration::class)->apply();
        $this->assertSame(0, app(Maintenance::class)->run()['renewals']);
        $setting->update(['values' => ['renewal_days' => 15, 'renewals_enabled' => true]]);
        app(SiteConfiguration::class)->apply();
        $this->assertSame(1, app(Maintenance::class)->run()['renewals']);
        $this->assertSame(0, app(Maintenance::class)->run()['renewals']);
    }

    public function test_reminder_switch_and_lead_time_change_execution(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        Invoice::create(['user_id' => $u->id, 'type' => 'renewal', 'total_minor' => 1000, 'snapshot' => [], 'due_date' => today()->addDays(3), 'created_at' => now()->subDay()]);
        $s = OperationalSetting::create(['section' => 'automation', 'values' => ['reminder_days' => 5, 'reminders_enabled' => false]]);
        app(SiteConfiguration::class)->apply();
        $this->assertSame(0, app(Reminders::class)->run());
        $s->update(['values' => ['reminder_days' => 5, 'reminders_enabled' => true]]);
        app(SiteConfiguration::class)->apply();
        $this->assertSame(1, app(Reminders::class)->run());
        $this->assertSame(0, app(Reminders::class)->run());
    }

    public function test_validation_preserves_non_secret_draft_only_for_its_section(): void
    {
        $this->actingAs($this->root());
        $this->from(route('admin.settings.operation', 'billing'))->post(route('admin.settings.operation.save', 'billing'), [
            'version' => 0, 'ack' => 1, 'password' => 'wrong',
            'values' => $this->values('billing', ['issuer_name' => 'Nome mantido', 'issuer_details' => 'Contato mantido']),
        ])->assertSessionHasErrors('password');
        $this->assertSame('Nome mantido', session('_old_input.values.issuer_name'));
        $this->assertNull(session('_old_input.password'));
        $this->get(route('admin.settings.operation', 'billing'))->assertSee('Nome mantido')->assertSee('Contato mantido');
        $this->get(route('admin.settings.operation', 'support'))->assertDontSee('Nome mantido');
    }

    public function test_unknown_fields_are_not_preserved_in_session(): void
    {
        $this->actingAs($this->root());
        $this->save('payments', ['unexpected_secret' => 'DO-NOT-FLASH'])->assertSessionHasErrors('values');
        $this->assertStringNotContainsString('DO-NOT-FLASH', json_encode(session('_old_input')));
    }

    public function test_local_assets_are_content_versioned_and_same_origin(): void
    {
        $this->assertMatchesRegularExpression('~^/assets/admin-forms\.js\?v=[a-f0-9]{12}$~', panel_asset('assets/admin-forms.js'));
        $this->actingAs($this->root())->get(route('admin.settings.general'))->assertOk()->assertSee(panel_asset('assets/admin-forms.js'), false);
    }
}
