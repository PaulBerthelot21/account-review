<?php

namespace Cordon\AccountReview;

class EntityLocator
{
    private array $exportableEntities = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

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

    public function getExcludedFields(string $className): array
    {
        return $this->config['entities'][$className]['exclude_fields'] ?? [];
    }
}
