<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Metadata;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * The query parameters of a GET that names an input class (`input: EventSearchInput::class`): its properties, with
 * the description, the JSON schema and the requirement API Platform reads from them for the MCP tool taking the same
 * class. One class describes what the GET and the tool take, QueryInputMapper reads it from either.
 *
 * Runs before API Platform's own parameter factory (priority 1000), which completes them as the parameters an
 * operation declares.
 */
#[AsDecorator('api_platform.metadata.resource.metadata_collection_factory', priority: 1100)]
final readonly class QueryInputParameterResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private ResourceMetadataCollectionFactoryInterface $decorated,
        #[Autowire(service: 'api_platform.metadata.property.name_collection_factory')]
        private PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory,
        #[Autowire(service: 'api_platform.metadata.property.metadata_factory')]
        private PropertyMetadataFactoryInterface $propertyMetadataFactory,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resources = $this->decorated->create($resourceClass);

        foreach ($resources as $i => $resource) {
            $operations = $resource->getOperations();
            if (null === $operations) {
                continue;
            }

            foreach ($operations as $name => $operation) {
                $inputClass = $this->getInputClass($operation);
                if (null === $inputClass) {
                    continue;
                }

                $operations->add($name, $operation->withParameters($this->addParameters($operation, $inputClass)));
            }

            $resources[$i] = $resource->withOperations($operations);
        }

        return $resources;
    }

    /**
     * @return class-string|null
     */
    private function getInputClass(mixed $operation): ?string
    {
        if (!$operation instanceof HttpOperation || HttpOperation::METHOD_GET !== $operation->getMethod()) {
            return null;
        }

        $input = $operation->getInput();
        $class = \is_array($input) ? ($input['class'] ?? null) : $input;

        return \is_string($class) && class_exists($class) ? $class : null;
    }

    /**
     * @param class-string $inputClass
     */
    private function addParameters(HttpOperation $operation, string $inputClass): Parameters
    {
        $declared = $operation->getParameters();
        $parameters = null === $declared ? new Parameters() : clone $declared;

        foreach ($this->propertyNameCollectionFactory->create($inputClass) as $property) {
            // A parameter the operation declares itself wins
            if ($parameters->has($property, QueryParameter::class)) {
                continue;
            }

            $metadata = $this->propertyMetadataFactory->create($inputClass, $property);
            $schema = $metadata->getSchema() ?? [];
            $required = $metadata->isRequired() ?? false;
            $parameters->add($property, new QueryParameter(
                key: $property,
                schema: self::getQuerySchema($schema, $required),
                // A property whose schema is written out keeps its description in it
                description: $metadata->getDescription() ?? $schema['description'] ?? null,
                required: $required,
            ));
        }

        return $parameters;
    }

    /**
     * The schema of a property as a query parameter's: a query string has no null, so the type of a nullable property
     * is the other one; the description is the parameter's, and a required one has no default.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function getQuerySchema(array $schema, bool $required): array
    {
        if (\is_array($schema['type'] ?? null)) {
            $types = array_values(array_diff($schema['type'], ['null']));
            $schema['type'] = 1 === \count($types) ? $types[0] : $types;
        }

        foreach (['anyOf', 'oneOf'] as $keyword) {
            if (!isset($schema[$keyword]) || !\is_array($schema[$keyword])) {
                continue;
            }

            $choices = array_values(array_filter($schema[$keyword], static fn (mixed $choice): bool => !\is_array($choice) || 'null' !== ($choice['type'] ?? null)));
            unset($schema[$keyword]);
            $schema = 1 === \count($choices) && \is_array($choices[0]) ? [...$schema, ...$choices[0]] : [...$schema, $keyword => $choices];
        }

        if (isset($schema['enum']) && \is_array($schema['enum'])) {
            $schema['enum'] = array_values(array_filter($schema['enum'], static fn (mixed $value): bool => null !== $value));
        }

        unset($schema['externalDocs'], $schema['description']);
        if ($required) {
            unset($schema['default']);
        }

        return $schema;
    }
}
