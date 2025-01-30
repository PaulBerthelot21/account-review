<?php

namespace Cordon\AccountReview;

class EntityLocator
{
    private array $exportableEntities = [];

    public function addExportableEntity(string $alias, string $className): void
    {
        $this->exportableEntities[$alias] = $className;
    }

    public function getExportableEntities(): array
    {
        return $this->exportableEntities;
    }

    public function getEntityClass(string $alias): ?string
    {
        return $this->exportableEntities[$alias] ?? null;
    }
}
