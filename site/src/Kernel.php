<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Appelee par MicroKernelTrait via $this->getAllowedEnvs() ; protected
     * (plutot que private) pour refleter cet usage indirect.
     *
     * @return list<string> An array of allowed values for APP_ENV
     */
    protected function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
