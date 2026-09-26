<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Entity\City;
use App\Repository\CityRepository;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A city picked in the autocomplete of the cities (assets/js/pages/profile.js): the name the member types, and
 * the slug of the city they choose in the list, which becomes the City. Names are not unique (Saint-Denis), slugs are.
 */
final class CityPickerType extends AbstractType
{
    public function __construct(private readonly CityRepository $cityRepository)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => $options['required'],
                'attr' => [
                    'placeholder' => 'Toulouse, Lyon, Genève…',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('slug', HiddenType::class)
            ->addModelTransformer(new CallbackTransformer(
                static fn (?City $city): array => ['name' => $city?->getFullName(), 'slug' => $city?->getSlug()],
                function (?array $value): ?City {
                    $slug = trim((string) ($value['slug'] ?? ''));
                    if ('' === $slug) {
                        // An emptied field removes the city; a name typed without choosing in the list is an error
                        if ('' === trim((string) ($value['name'] ?? ''))) {
                            return null;
                        }

                        throw new TransformationFailedException('No city was chosen in the list.');
                    }

                    return $this->cityRepository->findOneBySlug($slug)
                        ?? throw new TransformationFailedException(\sprintf('No city has the slug "%s".', $slug));
                },
            ));
    }

    /**
     * {@inheritDoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'invalid_message' => 'Choisissez une ville dans la liste.',
            // Compound fields bubble their errors to the form by default: this one shows them under itself
            'error_bubbling' => false,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function getBlockPrefix(): string
    {
        return 'city_picker';
    }
}
