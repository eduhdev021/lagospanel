<?php

namespace Tests\Feature;

use App\Models\FinancialLedgerEntry;
use App\Models\Invoice;
use App\Models\OperationalSetting;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Notifications\InvoiceNotice;
use App\Services\Maintenance;
use App\Services\SiteConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutomaticRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_renewal_can_charge_wallet_once_when_opted_in(): void
    {
        Notification::fake();
        $user = User::factory()->create(['balance_minor' => 1500]);
        Service::create([
            'user_id' => $user->id,
            'name' => 'Hospedagem automática',
            'status' => 'active',
            'cycle' => 'monthly',
            'price_minor' => 1000,
            'next_due' => today(),
            'auto_renew' => true,
        ]);
        OperationalSetting::create(['section' => 'automation', 'values' => [
            'renewals_enabled' => true,
            'renewal_days' => 5,
            'auto_charge_wallet' => true,
        ]]);
        app(SiteConfiguration::class)->apply();

        $result = app(Maintenance::class)->run();
        $invoice = Invoice::where('type', 'renewal')->sole();

        $this->assertSame(1, $result['renewals']);
        $this->assertSame(1, $result['renewals_auto_charged']);
        $this->assertSame(0, $result['renewals_auto_charge_skipped']);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(500, $user->fresh()->balance_minor);
        $this->assertSame('wallet', Payment::where('invoice_id', $invoice->id)->sole()->gateway);
        $this->assertDatabaseHas('financial_ledger_entries', [
            'invoice_id' => $invoice->id,
            'type' => 'payment',
            'amount_minor' => 1000,
        ]);
        Notification::assertSentTo($user, InvoiceNotice::class);
        $this->assertSame(0, app(Maintenance::class)->run()['renewals']);
        $this->assertSame(1, FinancialLedgerEntry::where('invoice_id', $invoice->id)->count());
    }
}
