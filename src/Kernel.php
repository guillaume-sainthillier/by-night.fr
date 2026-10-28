<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App;

use App\DependencyInjection\Compiler\AsyncElasticaPersisterPass;
use App\DependencyInjection\Compiler\BatchResetCompilerPass;
use App\DependencyInjection\Compiler\TwoFactorAccessListenerPass;
use Locale;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * The site speaks French only (framework.default_locale): ICU formats in it without being told, Twig's intl filters
     * (format_date, format_number…) included, in pages, e-mails and commands alike, whatever intl.default_locale the
     * server has (en_US_POSIX when unset).
     */
    public function boot(): void
    {
        parent::boot();

        Locale::setDefault($this->getContainer()->getParameter('kernel.default_locale'));
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AsyncElasticaPersisterPass());
        $container->addCompilerPass(new BatchResetCompilerPass());
        $container->addCompilerPass(new TwoFactorAccessListenerPass());
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     *
     * @phpstan-ignore method.unused
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }
}
