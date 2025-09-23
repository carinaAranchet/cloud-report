<?php

namespace App\Services\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class NextcloudProvider extends AbstractProvider implements ProviderInterface
{
    protected $scopes = ['openid','profile','email'];

    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            config('services.oidc.authorize_url'), $state
        );
    }

    protected function getTokenUrl()
    {
        return config('services.oidc.token_url');
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            config('services.oidc.userinfo_url'),
            ['headers' => ['Authorization' => 'Bearer '.$token]]
        );

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'    => $user['sub'] ?? null,
            'name'  => $user['name'] ?? $user['preferred_username'] ?? null,
            'email' => $user['email'] ?? null,
        ]);
    }
}
