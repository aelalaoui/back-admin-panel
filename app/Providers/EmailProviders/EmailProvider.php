<?php

namespace App\Providers\EmailProviders;

Abstract class EmailProvider
{
    public function __construct($withThrottle = false)
    {
        //$this->setThrottleActive($withThrottle);
    }

    /**
     * @param array $params
     * @return array
     */
    abstract protected function paramsToSend(array $params): array;

    /**
     * @param string $template
     * @param array $to
     * @param array $cc
     * @param array $bcc
     * @param array $params
     * @param string|null $subject
     * @param string|null $forceFrom
     * @return array
     */
    abstract public function sendEmail(
        string $template,
        array $to,
        array $cc = [],
        array $bcc = [],
        array $params = [],
        string $subject = null,
        string $forceFrom = null
    ): array;
}
