<?php
/**
 * LagosPanel — Troca de URL segura para dados serializados do WordPress
 *
 * Uso (NA RAIZ do WordPress, no servidor de destino):
 *   php replace-url.php http://url-antiga https://url-nova
 *
 * Por que não um simples SQL REPLACE: o WordPress armazena muitos valores
 * SERIALIZADOS (a:N:{s:...}) com o TAMANHO da string embutido — um REPLACE
 * direto corrompe esses dados. Este script percorre os valores, e quando um
 * valor serializado muda, ele deserializa → substitui → re-serializa.
 */
if (PHP_SAPI !== 'cli') exit("Somente CLI.\n");
if (!file_exists(__DIR__ . '/wp-load.php')) exit("Rode este script na raiz do WordPress (onde fica o wp-load.php).\n");

$old = $argv[1] ?? '';
$new = $argv[2] ?? '';
foreach ([$old, $new] as $u) {
    if (!preg_match('#^https?://[a-z0-9.\-]+(:\d+)?$#i', rtrim($u, '/'))) {
        exit("Use: php replace-url.php http://url-antiga https://url-nova  (sem barra final)\n");
    }
}
$old = rtrim($old, '/');
$new = rtrim($new, '/');
if ($old === $new) exit("URLs iguais — nada a fazer.\n");

require __DIR__ . '/wp-load.php';

function lagos_sr($data, $old, $new) {
    if (is_array($data)) {
        $out = [];
        foreach ($data as $k => $v) $out[lagos_sr($k, $old, $new)] = lagos_sr($v, $old, $new);
        return $out;
    }
    if (is_object($data)) {
        foreach (get_object_vars($data) as $k => $v) $data->$k = lagos_sr($v, $old, $new);
        return $data;
    }
    if (!is_string($data)) return $data;
    $plain = str_replace($old, $new, $data);
    if ($plain !== $data && is_serialized($data)) {
        if (is_serialized($plain)) return $plain;          //replace não quebrou
        $un = @unserialize($data);
        if ($un !== false) return serialize(lagos_sr($un, $old, $new)); //recursivo corrige tamanhos
        return $data; // não conseguiu — preserva original
    }
    return $plain;
}

global $wpdb;

$alvos = [
    [$wpdb->options,  'option_id',  ['option_value']],
    [$wpdb->postmeta, 'meta_id',    ['meta_value']],
    [$wpdb->usermeta, 'umeta_id',   ['meta_value']],
    [$wpdb->posts,    'ID',         ['post_content', 'guid', 'post_excerpt', 'post_title']],
    [$wpdb->comments, 'comment_ID', ['comment_content', 'comment_author_url']],
];

$total = 0;
echo "Trocando:\n  $old\n→ $new\n\n";
foreach ($alvos as [$table, $pk, $cols]) {
    $colList = implode(',', array_merge([$pk], $cols));
    $rows  = $wpdb->get_results("SELECT $colList FROM $table", ARRAY_A);
    $count = 0;
    foreach ($rows as $row) {
        $changed = false;
        foreach ($cols as $col) {
            $val = $row[$col];
            if (!is_string($val) || strpos($val, $old) === false) continue;
            $nv = lagos_sr($val, $old, $new);
            if ($nv !== $val) {
                $wpdb->query($wpdb->prepare("UPDATE $table SET $col = %s WHERE $pk = %s", $nv, $row[$pk]));
                $changed = true;
            }
        }
        if ($changed) $count++;
    }
    $total += $count;
    printf("  %-20s %d linha(s) atualizada(s)\n", $table, $count);
}

delete_option('rewrite_rules'); // regenera permalinks
echo "\nOK: $total linha(s) atualizadas. Permalinks serão regenerados no próximo acesso.\n";
echo "Confira: " . $new . "/  e o wp-admin.\n";
