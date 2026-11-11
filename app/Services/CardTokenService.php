<?php

namespace App\Services;

use App\Models\MemberCard;
use RuntimeException;

class CardTokenService
{
    private const PREFIX = 'RPK1';

    private const MAX_LENGTH = 200;

    public function make(MemberCard $card): string
    {
        $payload = self::PREFIX.".{$card->getKey()}.{$card->version}";

        return $payload.'.'.$this->sign($payload);
    }

    /**
     * Kembalikan kartu aktif untuk token ini, atau null untuk alasan apa pun (alasan tidak dibocorkan).
     */
    public function resolve(string $token): ?MemberCard
    {
        $token = trim($token);

        if (strlen($token) > self::MAX_LENGTH
            || ! preg_match('/^RPK1\.(\d+)\.(\d+)\.([A-Za-z0-9_-]{43})$/', $token, $m)) {
            return null;
        }

        [, $id, $version, $sig] = $m;

        if (! hash_equals($this->sign(self::PREFIX.".{$id}.{$version}"), $sig)) {
            return null;
        }

        return MemberCard::query()
            ->whereKey((int) $id)
            ->where('version', (int) $version)
            ->whereNull('revoked_at')
            ->first();
    }

    private function sign(string $payload): string
    {
        $secret = config('repkard.card_secret');

        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException('CARD_SECRET belum diisi di .env.');
        }

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $secret, true)), '+/', '-_'), '=');
    }
}
