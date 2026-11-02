<?php

namespace App\Services\FragmentTool\Ai;

use RuntimeException;

class GeminiException extends RuntimeException
{
    public static function fromStatus(int $status, string $body = ''): self
    {
        $reason = match (true) {
            $status === 429 => 'quota dépassé ou trop de requêtes',
            $status === 503 => 'service surchargé, réessayez dans un instant',
            $status === 401, $status === 403 => 'clé API refusée',
            $status === 400 => 'requête refusée',
            default => "erreur HTTP {$status}",
        };

        return new self("Gemini : {$reason}.".($body !== '' ? ' '.mb_substr($body, 0, 300) : ''));
    }
}
