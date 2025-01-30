<?php

namespace Cordon\AccountReview\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ExportableEntityPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container): void
    {
        $locator = $container->getDefinition('cordon.account_review.entity_locator');
        $entities = $container->findTaggedServiceIds('cordon.exportable_entity');

        foreach ($entities as $id => $tags) {
            foreach ($tags as $tag) {
                $alias = $tag['alias'] ?? $id;
                $className = $container->getDefinition($id)->getClass();
                $locator->addMethodCall('addExportableEntity', [$alias, $className]);
            }
        }
    }
}
