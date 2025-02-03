<?php

namespace Cordon\AccountReview;

class EntityLocator
{
    private array $exportableEntities = [];

    public function addExportableEntity(string $className): void
    {
        $this->exportableEntities[] = $className;
    }

    public function getExportableEntities(): array
    {
        return $this->exportableEntities;
    }

    public function getEntityClass(string $className): ?string
    {
        return in_array($className, $this->exportableEntities) ? $className : null;
    }
}
