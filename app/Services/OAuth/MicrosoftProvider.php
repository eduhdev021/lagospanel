<?php

namespace App\Services\OAuth;

use Laravel\Socialite\Two\InvalidStateException;
use SocialiteProviders\Microsoft\Provider;

class MicrosoftProvider extends Provider
{
    public function getClaims(): ?\stdClass
    {
        $claims = parent::getClaims();
        // This driver requests openid. Require a signed token and exact audience,
        // strengthening the upstream provider's substring audience comparison.
        if (! $claims || ! in_array($this->clientId, (array) ($claims->aud ?? []), true)) {
            throw new InvalidStateException('Invalid Microsoft audience');
        }

        return $claims;
    }
}
