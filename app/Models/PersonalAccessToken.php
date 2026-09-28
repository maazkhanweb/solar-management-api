<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * User columns required by the authenticated application.
     */
    private const USER_COLUMNS = [
        'id',
        'name',
        'email',
        'email_verified_at',
        'phone',
        'area_id',
        'password',
        'remember_token',
        'role',
        'profile_image',
        'status',
        'last_login',
        'created_at',
        'updated_at',
    ];

    /**
     * Find a token and its User tokenable in one query for normal id|secret tokens.
     */
    public static function findToken($token)
    {
        if (strpos($token, '|') === false) {
            return parent::findToken($token);
        }

        [$id, $plainTextToken] = explode('|', $token, 2);

        if (! ctype_digit($id) || $plainTextToken === '') {
            return parent::findToken($token);
        }

        $accessToken = static::query()
            ->leftJoin('users', function ($join) {
                $join->on('personal_access_tokens.tokenable_id', '=', 'users.id')
                    ->where(
                        'personal_access_tokens.tokenable_type',
                        User::class
                    );
            })
            ->where('personal_access_tokens.id', $id)
            ->select('personal_access_tokens.*')
            ->addSelect(array_map(
                fn ($column) => "users.{$column} as sanctum_user_{$column}",
                self::USER_COLUMNS
            ))
            ->first();

        if (! $accessToken || ! hash_equals(
            $accessToken->token,
            hash('sha256', $plainTextToken)
        )) {
            return;
        }

        if ($accessToken->tokenable_type !== User::class) {
            return parent::findToken($token);
        }

        if ($accessToken->sanctum_user_id === null) {
            return $accessToken->setRelation('tokenable', null);
        }

        $attributes = [];

        foreach (self::USER_COLUMNS as $column) {
            $attributes[$column] = $accessToken->getAttribute(
                "sanctum_user_{$column}"
            );
        }

        $user = (new User())->newFromBuilder($attributes);

        return $accessToken->setRelation('tokenable', $user);
    }
}