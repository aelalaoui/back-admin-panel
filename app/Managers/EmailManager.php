<?php

namespace App\Managers;

use App\Providers\EmailProviders\EmailProvider;
use App\Providers\EmailProviders\MailCoachProvider;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator as V;
use Illuminate\Validation\Validator;

class EmailManager
{
    /** @var EmailProvider $provider */
    protected EmailProvider $provider;

    protected V|Validator $validator;
    private ?string $subject;
    private array $to;
    private array $cc;
    private array $bcc;

    /**
     * @param array $to
     * @param array $cc
     * @param array $bcc
     * @param ?string $subject
     * @param bool $withThrottle
     * @throws Exception
     */
    public function __construct(
        array $to,
        array $cc = [],
        array $bcc = [],
        string $subject = null,
        bool $withThrottle = false,
    ) {
        $this->validate($subject, $to, $cc, $bcc, $withThrottle);
        if ($this->validator->fails()) {
            Log::error('Validation errors: ' . implode(', ', $this->validator->errors()->all()));
            throw new \InvalidArgumentException($this->validator->errors()->first());
        }
        $this->subject = $subject;
        $this->to = array_map([self::class, 'guardEmail'], $to);
        $this->cc = array_map([self::class, 'guardEmail'], $cc);
        $this->bcc = array_map([self::class, 'guardEmail'], $bcc);
        $this->provider = $this->getEmailProvider($withThrottle);
    }

    /**
     * @param bool $withThrottle
     * @return MailCoachProvider
     * @throws Exception
     */
    private function getEmailProvider(bool $withThrottle): EmailProvider
    {
        return new MailCoachProvider($withThrottle);
    }

    /**
     * @param string $template
     * @param array $params
     * @param string|null $forceFrom
     * @return array
     */
    public function sendEmail(
        string $template,
        array $params = [],
        string $forceFrom = null
    ): array {
        if ($this->canSend()) {
            return $this->provider->sendEmail(
                $template,
                $this->to,
                $this->cc,
                $this->bcc,
                $params,
                $this->subject,
                $forceFrom
            );
        }
        $this->logMessage($template, $params);
        return [];
    }

    /**
     * @param string $email
     * @return string
     */
    private static function guardEmail(string $email): string
    {
        //if (Config::isProdEnv() || Config::isDemoEnv()) {
            return $email;
        //}

        //return static::rerouteToInternal($email);
    }

    /**
     * @param string $email
     * @return string
     */
    private static function rerouteToInternal(string $email): string
    {
        if (!strpos($email, "@plussimple.") && !strpos($email, "@mail.plussimple.")) {
            $email = implode("+", explode("@", $email)) . "@mail.plussimple.io";
        }

        return $email;
    }

    /**
     * @return bool
     */
    private function canSend(): bool
    {
        return config('mail.should-send');
    }

    /**
     * @param string $template
     * @param array $params
     * @return void
     */
    private function logMessage(string $template, array $params): void
    {
        Log::info(sprintf(
            "[SANDBOX] Sent an email with template %s to %s with those params %s",
            $template,
            var_export($this->to, true),
            var_export($params, true)
        ));
    }

    /**
     * @param string|null $subject
     * @param array $to
     * @param array $cc
     * @param array $bcc
     * @param bool $withThrottle
     * @return void
     */
    private function validate(
        ?string $subject,
        array $to,
        array $cc,
        array $bcc,
        bool $withThrottle,
    ): void {
        $this->validator = V::make(
            [
                'subject' => $subject ?? '',
                'to' => $to,
                'cc' => $cc,
                'bcc' => $bcc,
                'withThrottle' => $withThrottle,
            ],
            [
                'subject' => 'string',
                'to' => 'required|array|email_array',
                'cc' => 'array|email_array',
                'bcc' => 'array|email_array',
                'withThrottle' => 'boolean',
            ],
            [
                'subject.string' => 'The subject must be a string.',
                'to.required' => 'The to field is required.',
                'to.array' => 'The to field must be an array.',
                'to.email_array' => 'Each element in the $to array must be a valid email address.',
                'cc.array' => 'The cc field must be an array.',
                'cc.email_array' => 'Each element in the $cc array must be a valid email address.',
                'bcc.array' => 'The bcc field must be an array.',
                'bcc.email_array' => 'Each element in the $bcc array must be a valid email address.',
                'withThrottle.boolean' => 'The withThrottle field must be a boolean.',
            ]
        );
    }
}
