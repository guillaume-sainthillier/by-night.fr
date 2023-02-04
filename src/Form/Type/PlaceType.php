<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Dto\CountryDto;
use App\Dto\PlaceDto;
use App\Entity\Country;
use App\Repository\CountryRepository;
use Doctrine\Common\Collections\Criteria;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceType extends AbstractType
{
    public function __construct(private readonly CountryRepository $countryRepository)
    {
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'Nom du lieu',
                'attr' => [
                    'placeholder' => 'Indiquez le nom du lieu',
                ],
            ])
            ->add('street', TextType::class, [
                'label' => 'Rue',
                'required' => false,
            ])
            ->add('latitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('city', CityType::class, [
                'label' => false,
            ])
            ->add('country', EntityType::class, [
                'label' => 'Pays',
                'placeholder' => '?',
                'class' => Country::class,
                'query_builder' => static fn (CountryRepository $er) => $er->createQueryBuilder('c')->orderBy('c.name', Criteria::ASC),
                'choice_label' => 'name',
            ]);

        $builder->get('latitude')->addModelTransformer(new CallbackTransformer(
            static fn ($latitude) => (float) $latitude ?: null,
            static fn ($latitude) => (float) $latitude ?: null
        ));

        $builder->get('longitude')->addModelTransformer(new CallbackTransformer(
            static fn ($latitude) => (float) $latitude ?: null,
            static fn ($latitude) => (float) $latitude ?: null
        ));

        $builder->get('country')
            ->addModelTransformer(new CallbackTransformer(
                function (?CountryDto $dto) {
                    if (null === $dto?->entityId) {
                        return null;
                    }

                    return $this->countryRepository->find($dto->entityId);
                },
                static function (?Country $country) {
                    if (null === $country) {
                        return null;
                    }

                    $dto = new CountryDto();
                    $dto->entityId = $country->getId();
                    $dto->code = $country->getId();

                    return $dto;
                }
            ));
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => PlaceDto::class,
        ]);
    }
}
