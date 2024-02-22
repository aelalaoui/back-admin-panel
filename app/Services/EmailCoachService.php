<?php

namespace App\Services;

use Exception;

class EmailCoachService extends ClientLightService
{
    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (!self::isEnabled()) {
            throw new Exception(
                "MailCoach is not enabled, please check your env config",
                404,
            );
        }

        parent::__construct(
            config('services.mailcoach.url'),
            [
                'Content-Type' => "application/json",
                'Accept' => "application/json",
                'Authorization' => config('services.mailcoach.auth_key'),
            ]
        );
    }

    public static function isEnabled()
    {
        return config('services.mailcoach.enabled') ?? false;
    }
}
