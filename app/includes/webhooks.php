<?php
/**
 * LagosPanel Core — Webhooks de saída (v0.9)
 * Notifica sistemas externos em eventos do painel via POST JSON assinado (HMAC-SHA256).
 * Eventos: invoice.paid · service.activated · client.created
 *
 * © 2026 Lagos Soluções — Todos os direitos reservados.
 */
if (!defined('ABSPATH')) exit;

/**
 * Envia um webhook (se configurado) e registra a entrega.
 */
function lagos_webhook_send(string $event, array $data = []): void {
    $url = (string) get_option('lagos_webhook_url', '');
    if (!$url) return;

    $payload = (string) wp_json_encode(['event' => $event, 'at' => current_time('mysql'), 'data' => $data], JSON_UNESCAPED_UNICODE);
    $secret  = (string) get_option('lagos_webhook_secret', '');

    $r = wp_remote_post($url, [
        'timeout' => 10,
        'headers' => [
            'Content-Type'      => 'application/json',
            'X-Lagos-Event'     => $event,
            'X-Lagos-Signature' => hash_hmac('sha256', $payload, $secret),
        ],
        'body' => $payload,
    ]);

    $code = is_wp_error($r) ? 0 : (int) wp_remote_retrieve_response_code($r);
    $log  = get_option('lagos_webhook_log', []);
    array_unshift($log, [
        'at'    => current_time('mysql'),
        'event' => $event,
        'code'  => $code,
        'err'   => is_wp_error($r) ? $r->get_error_message() : '',
    ]);
    update_option('lagos_webhook_log', array_slice($log, 0, 50));
    lagos_audit('webhook', $event . ' → HTTP ' . $code);
}

/* ── Configuração (página Configurações) ── */
add_action('lagos_settings_extra', function () { ?>
    <h2 style="margin-top:32px">Webhooks de saída</h2>
    <p class="description">Notifica um sistema externo (POST JSON) quando acontecem eventos no painel. Cada requisição leva o header <code>X-Lagos-Signature: sha256-HMAC(payload, segredo)</code> para verificação.</p>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="lagos_webhook_url">URL de destino</label></th>
            <td><input type="url" id="lagos_webhook_url" name="lagos_webhook_url" value="<?php echo esc_attr(get_option('lagos_webhook_url', '')); ?>" class="regular-text" placeholder="https://seusistema.com.br/webhook"></td>
        </tr>
        <tr>
            <th><label for="lagos_webhook_secret">Segredo (HMAC)</label></th>
            <td><input type="text" id="lagos_webhook_secret" name="lagos_webhook_secret" value="<?php echo esc_attr(get_option('lagos_webhook_secret', '')); ?>" class="regular-text" placeholder="segredo compartilhado"></td>
        </tr>
        <tr>
            <th>Eventos enviados</th>
            <td><code>invoice.paid</code> · <code>service.activated</code> · <code>client.created</code></td>
        </tr>
        <tr>
            <th>Últimas entregas</th>
            <td>
                <?php $log = get_option('lagos_webhook_log', []); if ($log) : ?>
                <table class="wp-list-table widefat striped" style="max-width:560px;border-radius:8px;overflow:hidden">
                    <thead><tr><th>Quando</th><th>Evento</th><th>HTTP</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($log, 0, 8) as $e) : ?>
                        <tr>
                          <td><?php echo esc_html($e['at']); ?></td>
                          <td><code><?php echo esc_html($e['event']); ?></code></td>
                          <td style="font-weight:700;color:<?php echo ($e['code'] >= 200 && $e['code'] < 300) ? '#22B07D' : '#D64550'; ?>"><?php echo (int) $e['code'] ?: 'erro'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?><span class="description">Nenhuma entrega ainda.</span><?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
});

add_action('lagos_settings_saved', function () {
    update_option('lagos_webhook_url', esc_url_raw(wp_unslash($_POST['lagos_webhook_url'] ?? '')));
    update_option('lagos_webhook_secret', sanitize_text_field(wp_unslash($_POST['lagos_webhook_secret'] ?? '')));
});
