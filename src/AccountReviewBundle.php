<?php

namespace Cordon\AccountReview;

use Cordon\AccountReview\DependencyInjection\Compiler\ExportableEntityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AccountReviewBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ExportableEntityPass());
    }
}
