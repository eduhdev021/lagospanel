<?php

namespace App\Support;

use App\Models\OperationalSetting;
use Illuminate\Support\Facades\Schema;

final class OperationalSettings
{
    // field: [label, config path, type, minimum, maximum]
    public const SECTIONS = [
        'billing' => ['title' => 'Faturamento e documentos', 'icon' => 'invoice', 'description' => 'Identificação do emissor, reservas de pedidos e vencimento de recargas. Moeda BRL; documentos não fiscais.', 'fields' => [
            'issuer_name' => ['Nome do emissor nas faturas', 'documents.issuer_name', 'text', 1, 150],
            'issuer_details' => ['Dados e contato do emissor', 'documents.issuer_details', 'textarea', 0, 2000],
            'reservation_minutes' => ['Prazo da reserva de pedidos (minutos)', 'lagos.reservation_minutes', 'integer', 15, 10080],
            'deposit_due_days' => ['Vencimento de novas recargas (dias)', 'automation.deposit_due_days', 'integer', 1, 30],
        ]],
        'automation' => ['title' => 'Automação de cobrança', 'icon' => 'clock', 'description' => 'Regras executadas pelo agendador existente. Mudanças afetam futuras execuções; faturas e operações já criadas não são revertidas.', 'fields' => [
            'renewals_enabled' => ['Gerar faturas de renovação automaticamente', 'automation.renewals_enabled', 'boolean', 0, 1],
            'renewal_days' => ['Gerar renovação antes do vencimento (dias)', 'automation.renewal_days', 'integer', 0, 60],
            'suspensions_enabled' => ['Agendar suspensão de serviços inadimplentes', 'automation.suspensions_enabled', 'boolean', 0, 1],
            'suspension_days' => ['Aguardar após o vencimento para suspender (dias)', 'automation.suspension_days', 'integer', 1, 90],
            'reminders_enabled' => ['Enviar lembretes de faturas por e-mail', 'automation.reminders_enabled', 'boolean', 0, 1],
            'reminder_days' => ['Antecedência do primeiro lembrete (dias)', 'automation.reminder_days', 'integer', 0, 30],
        ]],
        'payments' => ['title' => 'Gateways de pagamento', 'icon' => 'card', 'description' => 'Ative os provedores da sua operação com uma leitura clara de capacidades, ambiente e credenciais. A Efí usa Pix, OAuth2 e certificado mTLS.', 'fields' => [
            'live' => ['Permitir pagamentos reais (produção)', 'lagos.payments.live', 'boolean', 0, 1],
            'stripe_enabled' => ['Habilitar Stripe', 'lagos.payments.stripe.enabled', 'boolean', 0, 1],
            'stripe_secret' => ['Stripe — chave secreta', 'lagos.payments.stripe.secret', 'secret', 0, 2000],
            'stripe_webhook' => ['Stripe — segredo de assinatura do webhook', 'lagos.payments.stripe.webhook_secret', 'secret', 0, 2000],
            'mp_enabled' => ['Habilitar Mercado Pago', 'lagos.payments.mercadopago.enabled', 'boolean', 0, 1],
            'mp_token' => ['Mercado Pago — access token', 'lagos.payments.mercadopago.token', 'secret', 0, 2000],
            'efi_enabled' => ['Habilitar Efí Bank', 'lagos.payments.efi.enabled', 'boolean', 0, 1],
            'efi_environment' => ['Efí — ambiente da API (homologacao ou producao)', 'lagos.payments.efi.environment', 'text', 1, 16],
            'efi_client_id' => ['Efí — Client ID', 'lagos.payments.efi.client_id', 'secret', 0, 2000],
            'efi_client_secret' => ['Efí — Client Secret', 'lagos.payments.efi.client_secret', 'secret', 0, 2000],
            'efi_certificate_path' => ['Efí — caminho do certificado P12/PEM no servidor', 'lagos.payments.efi.certificate', 'text', 1, 512],
            'efi_certificate_password' => ['Efí — senha do certificado (se houver)', 'lagos.payments.efi.certificate_password', 'secret', 0, 512],
            'efi_certificate_type' => ['Efí — tipo do certificado (PEM ou P12)', 'lagos.payments.efi.certificate_type', 'text', 3, 3],
            'efi_pix_key' => ['Efí — chave Pix recebedora', 'lagos.payments.efi.pix_key', 'secret', 1, 200],
            'efi_webhook_hmac' => ['Efí — HMAC privado do webhook', 'lagos.payments.efi.webhook_hmac', 'secret', 16, 128],
            'efi_charge_expiration' => ['Efí — validade da cobrança (segundos)', 'lagos.payments.efi.charge_expiration', 'integer', 300, 86400],
        ]],
        'support' => ['title' => 'Atendimento e SLA', 'icon' => 'ticket', 'description' => 'Prazos internos por prioridade e cota de anexos. SLA aplicado a novos chamados ou à alteração explícita de prioridade, sem recalcular tickets antigos.', 'fields' => [
            'sla_low' => ['Prioridade baixa — resposta em horas', 'support.sla.low', 'integer', 1, 720],
            'sla_normal' => ['Prioridade normal — resposta em horas', 'support.sla.normal', 'integer', 1, 720],
            'sla_high' => ['Prioridade alta — resposta em horas', 'support.sla.high', 'integer', 1, 720],
            'sla_urgent' => ['Prioridade urgente — resposta em horas', 'support.sla.urgent', 'integer', 1, 720],
            'attachment_mib' => ['Cota de anexos por cliente (MiB)', 'support.account_attachment_mib', 'integer', 10, 1024],
        ]],
        'resources' => ['title' => 'Recursos e limites', 'icon' => 'settings', 'description' => 'Controles globais dos recursos implementados. Habilitar não instala workers nem configura provedores.', 'fields' => [
            'native_provisioning' => ['Permitir provisionamento nativo', 'lagos.native_provisioning', 'boolean', 0, 1],
            'outgoing_webhooks' => ['Permitir entregas de webhooks de saída', 'lagos.outgoing_webhooks', 'boolean', 0, 1],
            'ai_daily_requests' => ['Solicitações de IA por cliente/dia (UTC)', 'ai.daily_requests', 'integer', 1, 500],
        ]],
    ];

    public static function keys(): array
    {
        $keys = ['support.account_attachment_bytes'];
        foreach (self::SECTIONS as $s) {
            foreach ($s['fields'] as $f) {
                $keys[] = $f[1];
            }
        }

        return array_unique($keys);
    }

    public static function apply(): array
    {
        if (! Schema::hasTable('operational_settings')) {
            return [];
        }
        $applied = [];
        foreach (OperationalSetting::all() as $setting) {
            $section = self::SECTIONS[$setting->section] ?? null;
            if (! $section) {
                continue;
            }
            foreach ($setting->values ?? [] as $field => $value) {
                if (isset($section['fields'][$field])) {
                    config([$section['fields'][$field][1] => $value]);
                    $applied[] = $section['fields'][$field][1];
                }
            }
            if ($setting->section === 'support' && array_key_exists('attachment_mib', $setting->values ?? [])) {
                config(['support.account_attachment_bytes' => (int) $setting->values['attachment_mib'] * 1048576]);
                $applied[] = 'support.account_attachment_bytes';
            }
        }

        return array_unique($applied);
    }
}
