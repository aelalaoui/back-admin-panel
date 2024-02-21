<?php

namespace App\Managers;

class EmailManager
{
    public mixed $provider;

    public function __construct()
    {
        $this->provider = $this->getProvider();
    }

    private function getProvider(): bool
    {
        return true;
    }
}
