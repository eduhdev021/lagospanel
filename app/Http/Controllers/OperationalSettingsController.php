<?php

namespace App\Http\Controllers;

use App\Models\OperationalSetting;
use App\Services\AdminConfirmation;
use App\Services\Audit;
use App\Support\OperationalSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalSettingsController extends Controller
{
    public function index(Request $r, string $section)
    {
        abort_unless($r->user()->is_admin, 403);
        abort_unless(isset(OperationalSettings::SECTIONS[$section]), 404);

        return view('admin.configuration.operational', ['key' => $section, 'section' => OperationalSettings::SECTIONS[$section], 'setting' => OperationalSetting::find($section)]);
    }

    public function save(Request $r, string $section, AdminConfirmation $confirmation)
    {
        try {
            return $this->persist($r, $section, $confirmation);
        } catch (ValidationException $e) {
            if ($r->expectsJson()) {
                throw $e;
            }
            // Only known non-secret fields may survive validation redirects.
            $safe = [];
            foreach (OperationalSettings::SECTIONS[$section]['fields'] ?? [] as $name => $field) {
                if ($field[2] !== 'secret' && is_scalar($value = $r->input('values.'.$name))) {
                    $safe[$name] = $value;
                }
            }

            return back()->withErrors($e->errors())->withInput([
                '_operation_section' => $section,
                'version' => $r->input('version'),
                'values' => $safe,
            ]);
        }
    }

    private function persist(Request $r, string $section, AdminConfirmation $confirmation)
    {
        abort_unless($r->user()->is_admin, 403);
        abort_unless(isset(OperationalSettings::SECTIONS[$section]), 404);
        $fields = OperationalSettings::SECTIONS[$section]['fields'];
        $rules = ['version' => 'required|integer|min:0', 'ack' => 'accepted', 'values' => 'required|array:'.implode(',', array_keys($fields)), 'clear' => 'sometimes|array:'.implode(',', array_keys(array_filter($fields, fn ($f) => $f[2] === 'secret')))];
        foreach ($fields as $key => [$label,$path,$type,$min,$max]) {
            $rules['values.'.$key] = $type === 'boolean' ? 'required|boolean' : ($type === 'integer' ? "required|integer|min:$min|max:$max" : (($min ? 'required' : 'nullable')."|string|min:$min|max:$max"));
            if ($type === 'secret') {
                $rules['clear.'.$key] = 'sometimes|boolean';
            }
        }
        if ($section === 'payments') {
            $rules['values.efi_environment'] = 'required|in:homologacao,producao';
            $rules['values.efi_certificate_type'] = 'required|in:PEM,P12,pem,p12';
        }
        $v = $r->validate($rules);
        $confirmation->verify($r);
        DB::transaction(function () use ($r, $section, $fields, $v) {
            OperationalSetting::insertOrIgnore(['section' => $section, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $s = OperationalSetting::whereKey($section)->lockForUpdate()->firstOrFail();
            abort_unless($s->version === (int) $v['version'], 409, 'Configuração alterada. Recarregue antes de salvar.');
            $values = $s->values ?? [];
            foreach ($fields as $key => [$label,$path,$type]) {
                if ($type === 'secret') {
                    if ($r->boolean('clear.'.$key)) {
                        $values[$key] = null;
                    } elseif (! empty($v['values'][$key])) {
                        if (preg_match('/[\r\n]/', $v['values'][$key])) {
                            throw ValidationException::withMessages(['values.'.$key => 'Segredo inválido.']);
                        }$values[$key] = $v['values'][$key];
                    }
                } else {
                    $values[$key] = match ($type) {
                        'boolean' => (bool) $v['values'][$key],'integer' => (int) $v['values'][$key],default => $v['values'][$key] ?? ''
                    };
                }
            }
            if ($section === 'payments') {
                foreach (['stripe_enabled' => ['stripe_secret', 'stripe_webhook'], 'mp_enabled' => ['mp_token'], 'efi_enabled' => ['efi_client_id', 'efi_client_secret', 'efi_certificate_path', 'efi_pix_key', 'efi_webhook_hmac']] as $enabled => $needed) {
                    if ($values[$enabled]) {
                        foreach ($needed as $key) {
                            if (! (array_key_exists($key, $values) ? $values[$key] : config($fields[$key][1]))) {
                                throw ValidationException::withMessages(['values.'.$key => 'Informe a credencial antes de habilitar este gateway.']);
                            }
                        }
                    }
                }
            }
            if ($section === 'support' && ! ($values['sla_low'] >= $values['sla_normal'] && $values['sla_normal'] >= $values['sla_high'] && $values['sla_high'] >= $values['sla_urgent'])) {
                throw ValidationException::withMessages(['values.sla_urgent' => 'Prioridades maiores devem ter prazo menor ou igual às menores.']);
            }
            $s->update(['values' => $values, 'version' => $s->version + 1]);
            Audit::record('settings.operational_updated', 'settings:'.$section, ['version' => $s->version, 'fields' => array_keys($values)], $r->user()->id);
        }, 5);

        return back()->with('status', 'Configuração salva. As próximas requisições e tarefas usarão os novos valores.');
    }

    public function coverage()
    {
        return view('admin.configuration.coverage');
    }
}
