<?php

namespace App\Services;

class AiFailure extends \RuntimeException
{
    public const LABELS = [
        'connection' => 'Não foi possível conectar ao provedor de IA. A equipe deve conferir URL, DNS, TLS e disponibilidade.',
        'authentication' => 'O provedor recusou a chave de IA. A equipe precisa conferir a credencial salva.',
        'permission' => 'O provedor não autorizou a operação ou o modelo para esta chave.',
        'not_found' => 'A API ou o modelo não foi encontrado no provedor configurado.',
        'rate_limit' => 'O provedor atingiu um limite de uso ou cota. Tente mais tarde ou fale com a equipe.',
        'provider_unavailable' => 'O provedor de IA está indisponível no momento.',
        'invalid_response' => 'O provedor não devolveu uma resposta textual completa.',
        'configuration_changed' => 'A configuração de IA mudou durante esta solicitação. Envie uma nova mensagem.',
        'worker_expired' => 'A fila não concluiu esta solicitação no prazo. A equipe deve verificar o worker.',
        'internal' => 'A resposta não pôde ser concluída. A equipe deve verificar a operação do assistente.',
    ];

    public function __construct(public readonly string $reason)
    {
        parent::__construct(self::LABELS[$reason] ?? self::LABELS['internal']);
    }

    public static function code(?\Throwable $e): string
    {
        return $e instanceof self && isset(self::LABELS[$e->reason]) ? $e->reason : 'internal';
    }

    public static function message(?string $code): string
    {
        return self::LABELS[$code ?? ''] ?? self::LABELS['internal'];
    }
}
