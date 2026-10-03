<?php
/**
 * LagosPanel Core — Tickets por e-mail (v0.10)
 * Lê a caixa IMAP periodicamente: respostas de clientes às notificações
 * viram respostas no próprio ticket (via tag [LAGOS-#ID] no assunto) e
 * e-mails novos de clientes cadastrados abrem tickets automaticamente.
 *
 * © 2026 Lagos Soluções — Todos os direitos reservados.
 */
if (!defined('ABSPATH')) exit;

/* ═══ Configuração (admin → Configurações) ═══ */
add_action('lagos_settings_extra', function () { ?>
    <h2 style="margin-top:32px">Tickets por e-mail (IMAP)</h2>
    <p class="description">Respostas de clientes a notificações de ticket viram respostas no próprio ticket; e-mails novos de clientes cadastrados abrem tickets automaticamente. Verificação a cada 15 minutos (e manual).</p>
    <table class="form-table" role="presentation">
        <tr>
            <th>Ativar</th>
            <td><label><input type="checkbox" name="lagos_email_piping" <?php checked(get_option('lagos_email_piping', 0), 1); ?>> Verificar a caixa de entrada automaticamente</label></td>
        </tr>
        <tr>
            <th><label for="lagos_imap_host">Servidor IMAP</label></th>
            <td><input type="text" id="lagos_imap_host" name="lagos_imap_host" value="<?php echo esc_attr(get_option('lagos_imap_host', '')); ?>" class="regular-text" placeholder="imap.seudominio.com.br"></td>
        </tr>
        <tr>
            <th>Porta / Criptografia</th>
            <td>
                <input type="number" name="lagos_imap_port" value="<?php echo esc_attr((int) get_option('lagos_imap_port', 993)); ?>" style="width:90px">
                <label style="margin-left:12px"><input type="checkbox" name="lagos_imap_ssl" <?php checked(get_option('lagos_imap_ssl', 1), 1); ?>> TLS/SSL</label>
            </td>
        </tr>
        <tr>
            <th><label for="lagos_imap_user">Usuário</label></th>
            <td><input type="text" id="lagos_imap_user" name="lagos_imap_user" value="<?php echo esc_attr(get_option('lagos_imap_user', '')); ?>" class="regular-text"></td>
        </tr>
        <tr>
            <th><label for="lagos_imap_pass">Senha</label></th>
            <td><input type="password" id="lagos_imap_pass" name="lagos_imap_pass" value="<?php echo esc_attr((string) get_option('lagos_imap_pass', '')); ?>" class="regular-text" autocomplete="new-password"></td>
        </tr>
        <tr>
            <th>Executar agora</th>
            <td><a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lagos_email_fetch_now'), 'lagos_email_fetch_now')); ?>">Verificar caixa de entrada agora</a></td>
        </tr>
    </table>
    <?php
});

add_action('lagos_settings_saved', function () {
    update_option('lagos_email_piping', isset($_POST['lagos_email_piping']) ? 1 : 0);
    update_option('lagos_imap_host', sanitize_text_field(wp_unslash($_POST['lagos_imap_host'] ?? '')));
    update_option('lagos_imap_port', absint($_POST['lagos_imap_port'] ?? 993));
    update_option('lagos_imap_ssl', isset($_POST['lagos_imap_ssl']) ? 1 : 0);
    update_option('lagos_imap_user', sanitize_text_field(wp_unslash($_POST['lagos_imap_user'] ?? '')));
    update_option('lagos_imap_pass', (string) wp_unslash($_POST['lagos_imap_pass'] ?? ''));
});

/* ═══ Cron: a cada 15 minutos ═══ */
add_filter('cron_schedules', function ($s) {
    $s['lagos_15min'] = ['interval' => 900, 'display' => 'LagosPanel — 15 minutos'];
    return $s;
});
add_action('init', function () {
    if (!wp_next_scheduled('lagos_email_tick')) wp_schedule_event(time() + 900, 'lagos_15min', 'lagos_email_tick');
});
add_action('lagos_email_tick', function () {
    if ((int) get_option('lagos_email_piping', 0)) lagos_email_fetch();
});

add_action('admin_post_lagos_email_fetch_now', function () {
    if (!current_user_can('manage_options')) wp_die('Sem permissão.');
    check_admin_referer('lagos_email_fetch_now');
    $r = lagos_email_fetch();
    wp_safe_redirect(admin_url('admin.php?page=lagos-settings&pipe=' . ($r >= 0 ? $r : 'err')));
    exit;
});

/* ═══ Cliente IMAP minimalista (dependência-zero) ═══ */
class Lagos_Imap_Client {
    private $fp;
    private $n = 0;

    public function __construct($host, $port, $ssl) {
        $target = ($ssl ? 'ssl://' : '') . $host . ':' . (int) $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->fp = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->fp) throw new Exception('conexão: ' . $errstr);
        stream_set_timeout($this->fp, 25);
        $this->read_line(); // saudação do servidor
    }

    private function tag() {
        return 'a' . str_pad((string) (++$this->n), 3, '0', STR_PAD_LEFT);
    }

    private function read_line() {
        return fgets($this->fp, 8192);
    }

    private function read_until($tag) {
        $out = '';
        while (($line = $this->read_line()) !== false) {
            $out .= $line;
            if (strncmp($line, $tag . ' ', strlen($tag) + 1) === 0) break;
        }
        return $out;
    }

    private function cmd($line) {
        $tag = $this->tag();
        fwrite($this->fp, $tag . ' ' . $line . "\r\n");
        return $this->read_until($tag);
    }

    public function login($user, $pass) {
        $r = $this->cmd('LOGIN "' . str_replace(['"', '\\'], '', $user) . '" "' . str_replace(['"', '\\'], '', $pass) . '"');
        if (strpos($r, ' OK ') === false) throw new Exception('login recusado');
    }

    /** UIDs das mensagens não lidas */
    public function unread_uids() {
        $this->cmd('SELECT INBOX');
        $r = $this->cmd('SEARCH UNSEEN');
        if (!preg_match('/\*\s+SEARCH\s+([0-9 ]*)/', $r, $m)) return [];
        return array_values(array_filter(array_map('intval', preg_split('/\s+/', trim($m[1])))));
    }

    /** Mensagem bruta (sem marcar como lida) */
    public function fetch($uid) {
        $tag = $this->tag();
        fwrite($this->fp, $tag . " UID FETCH $uid (BODY.PEEK[])\r\n");
        $raw = '';
        $size = 0;
        while (($line = $this->read_line()) !== false) {
            if ($size > 0) {
                $need = $size - strlen($raw);
                $raw .= $need >= strlen($line) ? $line : substr($line, 0, $need);
                if (strlen($raw) >= $size) { $this->read_until($tag); break; }
                continue;
            }
            if (preg_match('/\{(\d+)\}\r?$/', rtrim($line, "\n"), $m)) { $size = (int) $m[1]; $raw = ''; continue; }
            if (strncmp($line, $tag . ' ', strlen($tag) + 1) === 0) break;
        }
        return $raw;
    }

    public function mark_seen($uid) {
        $this->cmd("UID STORE $uid +FLAGS (\\Seen)");
    }

    public function close() {
        if ($this->fp) {
            @fwrite($this->fp, 'a' . str_pad((string) (++$this->n), 3, '0', STR_PAD_LEFT) . " LOGOUT\r\n");
            @fclose($this->fp);
            $this->fp = null;
        }
    }
}

/* ═══ Busca e processamento ═══ */
function lagos_email_fetch() {
    $host = (string) get_option('lagos_imap_host', '');
    if ($host === '') return 0;
    try {
        $imap = new Lagos_Imap_Client($host, (int) get_option('lagos_imap_port', 993), (bool) get_option('lagos_imap_ssl', 1));
        $imap->login((string) get_option('lagos_imap_user', ''), (string) get_option('lagos_imap_pass', ''));
        $n = 0;
        foreach ($imap->unread_uids() as $uid) {
            $raw = $imap->fetch($uid);
            if (trim($raw) === '') continue;
            $msg = lagos_email_parse($raw);
            if ($msg) lagos_email_handle($msg['email'], $msg['subject'], $msg['body']);
            $imap->mark_seen($uid);
            $n++;
            if ($n >= 50) break; // limite por rodada
        }
        $imap->close();
        if ($n) lagos_audit('cron', "email piping: $n mensagem(ns) processada(s)");
        return $n;
    } catch (Throwable $e) {
        lagos_audit('cron', 'email piping falhou: ' . $e->getMessage());
        return -1;
    }
}

/** Decodifica uma mensagem bruta em [email, subject, body] */
function lagos_email_parse($raw) {
    $sep = strpos($raw, "\r\n\r\n") !== false ? "\r\n\r\n" : "\n\n";
    [$head, $body] = array_pad(explode($sep, $raw, 2), 2, '');
    $headFlat = preg_replace('/\r?\n[ \t]+/', ' ', $head);

    $subject = '';
    if (preg_match('/^subject:\s*(.*)$/im', $headFlat, $m)) $subject = trim($m[1]);
    if (function_exists('iconv_mime_decode')) {
        $subject = iconv_mime_decode($subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    }
    $email = '';
    if (preg_match('/^from:.*?<([^>]+)>/im', $headFlat, $m)) $email = trim($m[1]);
    elseif (preg_match('/^from:\s*(\S+@\S+)/im', $headFlat, $m)) $email = trim(rtrim($m[1], '>'));
    if ($email === '') return null;

    return ['email' => strtolower($email), 'subject' => (string) $subject, 'body' => lagos_email_body($headFlat, $body)];
}

/** Extrai o texto da mensagem (prioriza text/plain; limpa citações) */
function lagos_email_body($head, $body) {
    $ctype = '';
    if (preg_match('/^content-type:\s*([^;\r\n]+)/im', $head, $m)) $ctype = strtolower(trim($m[1]));
    $bound = '';
    if (preg_match('/boundary="?([^";\r\n]+)"?/i', $head, $m)) $bound = $m[1];

    $decode = static function ($chunk, $partHead) {
        $enc = '7bit';
        if (preg_match('/content-transfer-encoding:\s*(\S+)/i', $partHead, $m)) $enc = strtolower(trim($m[1]));
        if ($enc === 'base64') $chunk = base64_decode($chunk);
        elseif ($enc === 'quoted-printable') $chunk = quoted_printable_decode($chunk);
        $cs = '';
        if (preg_match('/charset=["\']?([A-Za-z0-9._-]+)/i', $partHead, $m)) $cs = strtoupper($m[1]);
        if ($cs !== '' && $cs !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $conv = @mb_convert_encoding($chunk, 'UTF-8', $cs);
            if ($conv !== false) $chunk = $conv;
        }
        return (string) $chunk;
    };

    if ($bound !== '' && strpos($ctype, 'multipart') !== false) {
        foreach (explode('--' . $bound, $body) as $part) {
            $part = ltrim($part, "\r\n-");
            if (trim($part) === '') continue;
            $psep = strpos($part, "\r\n\r\n") !== false ? "\r\n\r\n" : "\n\n";
            [$ph, $pb] = array_pad(explode($psep, $part, 2), 2, '');
            if (preg_match('/content-type:\s*text\/plain/i', $ph)) return lagos_email_clean($decode($pb, $ph));
        }
        return lagos_email_clean(strip_tags($body)); // multipart sem text/plain
    }
    return lagos_email_clean($decode($body, $head));
}

/** Remove citações/assinaturas de resposta */
function lagos_email_clean($text) {
    $lines = preg_split('/\r?\n/', (string) $text);
    $out = [];
    foreach ($lines as $i => $ln) {
        $t = trim($ln);
        if (preg_match('/^(>|Em \d{1,2}\/\d{1,2}\/\d{4}|On .+wrote:|--)([^\w]|$)/', $t)) break;
        if (preg_match('/^(De|From|Enviado|Sent|Assunto|Subject|Para|To):/i', $t) && $i > 0) break;
        $out[] = $ln;
    }
    return trim(implode("\n", $out));
}

/** Encaminha uma mensagem para o sistema de tickets */
function lagos_email_handle($email, $subject, $body) {
    $user = get_user_by('email', $email);
    if (!$user || !in_array('lagos_client', (array) $user->roles, true)) {
        lagos_audit('email_piping', 'e-mail ignorado (remetente não é cliente): ' . $email);
        return;
    }
    $body = trim((string) $body);
    if ($body === '') return;

    // resposta a um ticket existente ([LAGOS-#ID] no assunto)
    $tid = 0;
    if (preg_match('/LAGOS-#?(\d{1,10})/i', (string) $subject, $m)) $tid = (int) $m[1];
    if ($tid) {
        $t = get_post($tid);
        if ($t && $t->post_type === 'lagos_ticket' && (int) get_post_meta($tid, '_lagos_user', true) === (int) $user->ID) {
            wp_insert_comment([
                'comment_post_ID'      => $tid,
                'comment_content'      => $body,
                'comment_approved'     => 1,
                'user_id'              => $user->ID,
                'comment_author'       => $user->display_name,
                'comment_author_email' => $user->user_email,
            ]);
            update_post_meta($tid, '_lagos_status', 'open'); // cliente respondeu → aguarda a equipe
            lagos_audit('email_piping', 'resposta por e-mail no ticket #' . $tid, $user->ID);
            return;
        }
    }

    // novo ticket por e-mail
    $title = trim((string) $subject);
    if ($title === '') $title = 'Ticket por e-mail — ' . date_i18n('d/m H:i');
    $tid = wp_insert_post([
        'post_type'    => 'lagos_ticket',
        'post_status'  => 'publish',
        'post_title'   => sanitize_text_field($title),
        'post_content' => sanitize_textarea_field($body),
    ]);
    if (!$tid || is_wp_error($tid)) return;
    update_post_meta($tid, '_lagos_user', $user->ID);
    update_post_meta($tid, '_lagos_status', 'open');
    update_post_meta($tid, '_lagos_dept', 'general');
    update_post_meta($tid, '_lagos_priority', 'medium');
    if (function_exists('lagos_mail_ticket')) lagos_mail_ticket($tid, $user);
    lagos_audit('email_piping', 'novo ticket por e-mail de ' . $email . ': ' . $title, $user->ID);
}
