<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Api\Input;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use LogicException;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The input of a read operation, given by the query string of its GET or by the arguments of its MCP tool: one class
 * says what both take (QueryInputParameterResourceMetadataCollectionFactory lists it as the query parameters of the
 * GET). Read as Symfony's #[MapQueryString] reads a query string: its texts cast to the types of the properties, then
 * validated.
 */
final readonly class QueryInputMapper
{
    public function __construct(
        private DenormalizerInterface $denormalizer,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $context the context of the provider
     *
     * @return T
     */
    public function map(Operation $operation, array $context, string $class): object
    {
        if (($operation->getInput()['class'] ?? null) !== $class) {
            throw new LogicException(\sprintf('The operation "%s" does not take "%s" as its input.', $operation->getName(), $class));
        }

        $values = $context['mcp_data'] ?? ($context['request'] ?? null)?->query->all() ?? [];

        try {
            // "csv" casts texts to the types of the properties ("10" to 10), as #[MapQueryString] does
            $input = $this->denormalizer->denormalize($values, $class, CsvEncoder::FORMAT, [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
                'filter_bool' => true,
            ]);
        } catch (PartialDenormalizationException $exception) {
            throw new ValidationException($this->violationsOf($exception));
        }

        $this->validator->validate($input, ['operation' => $operation]);

        return $input;
    }

    private function violationsOf(PartialDenormalizationException $exception): ConstraintViolationList
    {
        $violations = new ConstraintViolationList();
        foreach ($exception->getNotNormalizableValueErrors() as $error) {
            $violations->add(new ConstraintViolation($this->getMessage($error), null, [], null, (string) $error->getPath(), null));
        }

        return $violations;
    }

    /**
     * The message #[MapQueryString] gives, in the validators' words.
     */
    private function getMessage(NotNormalizableValueException $error): string
    {
        $expected = $error->getExpectedTypes() ?? [];

        return [] === $expected
            ? $this->translator->trans('This value is not valid.', domain: 'validators')
            : $this->translator->trans('This value should be of type {{ type }}.', ['{{ type }}' => implode('|', $expected)], 'validators');
    }
}
