<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\InvoiceNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class Quotes
{
    public function save(User $actor, array $input, ?Quote $quote = null): Quote
    {
        abort_unless($actor->hasPermission('billing.manage'), 403);
        $v = Validator::make($input, ['user_id' => 'required|integer|exists:users,id', 'title' => 'required|string|max:180', 'terms' => 'required|string|max:20000', 'items' => 'required|array|min:1|max:50', 'items.*.name' => 'required|string|max:250', 'items.*.quantity' => 'required|integer|min:1|max:1000', 'items.*.unit_minor' => 'required|integer|min:1|max:100000000', 'valid_until' => 'required|date_format:Y-m-d|after_or_equal:today', 'payment_days' => 'required|integer|min:1|max:90', 'version' => $quote ? 'required|integer|min:1' : 'nullable|integer'])->validate();
        $total = 0;
        $items = [];
        foreach ($v['items'] as $item) {
            $total += (int) $item['quantity'] * (int) $item['unit_minor'];
            $items[] = ['name' => $item['name'], 'quantity' => (int) $item['quantity'], 'unit_minor' => (int) $item['unit_minor'], 'cycle' => 'onetime'];
        }
        if ($total > 1000000000) {
            throw ValidationException::withMessages(['items' => 'Total acima do limite de R$ 10.000.000,00.']);
        }

        return DB::transaction(function () use ($actor, $v, $items, $total, $quote) {
            $data = ['user_id' => (int) $v['user_id'], 'title' => $v['title'], 'terms' => $v['terms'], 'items' => $items, 'total_minor' => $total, 'valid_until' => $v['valid_until'], 'payment_days' => (int) $v['payment_days']];
            if ($quote) {
                $q = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
                abort_unless($q->status === 'draft' && $q->version === (int) $v['version'], 409);
                $q->update($data + ['version' => $q->version + 1]);
            } else {
                $q = Quote::create($data + ['author_id' => $actor->id]);
            }
            $q->refresh();
            Audit::record('quote.saved', 'quote:'.$q->id, ['version' => $q->version], $actor->id);

            return $q;
        }, 5);
    }

    public function transition(User $actor, Quote $quote, string $action, int $version): Quote
    {
        abort_unless($actor->hasPermission('billing.manage'), 403);
        abort_unless(in_array($action, ['send', 'withdraw'], true), 422);

        return DB::transaction(function () use ($actor, $quote, $action, $version) {
            $q = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            abort_unless($q->version === $version, 409);
            if ($action === 'send') {
                abort_unless($q->status === 'draft' && ! $q->expired(), 409);
                $q->update(['status' => 'sent', 'sent_at' => now(), 'version' => $version + 1]);
            } else {
                abort_unless(in_array($q->status, ['draft', 'sent'], true), 409);
                $q->update(['status' => 'withdrawn', 'version' => $version + 1]);
            }
            Audit::record('quote.'.$action, 'quote:'.$q->id, [], $actor->id);

            return $q;
        }, 5);
    }

    public function decide(User $user, Quote $quote, string $decision, int $version): Quote
    {
        abort_unless(in_array($decision, ['accept', 'decline'], true), 422);

        return DB::transaction(function () use ($user, $quote, $decision, $version) {
            $q = Quote::whereKey($quote->id)->lockForUpdate()->firstOrFail();
            abort_unless($q->user_id === $user->id, 404);
            abort_unless($user->hasVerifiedEmail(), 403);
            if (($decision === 'accept' && $q->status === 'accepted') || ($decision === 'decline' && $q->status === 'declined')) {
                return $q;
            }
            abort_unless($q->version === $version && $q->status === 'sent' && ! $q->expired(), 409);
            if ($decision === 'accept') {
                $invoice = Invoice::create(['user_id' => $user->id, 'type' => 'quote', 'total_minor' => $q->total_minor, 'currency' => 'BRL', 'snapshot' => $q->items, 'due_date' => today()->addDays($q->payment_days)]);
                $q->update(['status' => 'accepted', 'accepted_at' => now(), 'invoice_id' => $invoice->id, 'version' => $version + 1]);
                $user->notify(new InvoiceNotice($invoice->id));
            } else {
                $q->update(['status' => 'declined', 'version' => $version + 1]);
            }
            Audit::record('quote.'.$decision, 'quote:'.$q->id, ['version' => $version, 'invoice_id' => $q->invoice_id], $user->id);

            return $q;
        }, 5);
    }
}
