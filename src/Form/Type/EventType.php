<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Form\Type;

use App\Entity\Country;
use App\Entity\Event;
use App\Form\Builder\DateRangeBuilder;
use App\Handler\DoctrineEventHandler;
use App\Repository\CountryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class EventType extends AbstractType
{
    private DoctrineEventHandler $doctrineEventHandler;

    private DateRangeBuilder $dateRangeBuilder;

    public function __construct(DoctrineEventHandler $doctrineEventHandler, DateRangeBuilder $dateRangeBuilder)
    {
        $this->doctrineEventHandler = $doctrineEventHandler;
        $this->dateRangeBuilder = $dateRangeBuilder;
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        parent::finishView($view, $form, $options);
        $this->dateRangeBuilder->finishView($view, $form);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this->dateRangeBuilder->addDateFields($builder, 'dateDebut', 'dateFin');
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'placeholder' => 'Choisissez un titre accrocheur...',
                ],
            ])
            ->add('descriptif', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Décrivez votre événement...',
                ],
            ])
            ->add('imageFile', VichImageType::class, [
                'label' => 'Affiche / Flyer',
                'required' => false,
                'thumb_params' => ['h' => 200, 'w' => 400, 'thumb' => 1],
            ])
            ->add('horaires', TextType::class, [
                'label' => 'Horaires',
                'required' => false,
                'attr' => [
                    'placeholder' => 'A 20h, de 21h à minuit',
                ],
            ])
            ->add('tarif', TextType::class, [
                'label' => 'Tarif',
                'required' => false,
                'attr' => [
                    'placeholder' => '17€ avec préventes, 20€ sur place',
                ],
            ])
            ->add('categorieManifestation', TextType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Concert, Spectacle, ...',
                ],
            ])
            ->add('themeManifestation', TextType::class, [
                'label' => 'Thèmes',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Humour, Tragédie, Jazz, Rock, Rap, ...',
                ],
            ])
            ->add('adresse', TextType::class, [
                'required' => false,
                'label' => 'Adresse',
                'attr' => [
                    'placeholder' => 'Tapez votre adresse ici pour remplir les champs ci-dessous',
                ],
            ])
            ->add('placeName', TextType::class, [
                'required' => true,
                'label' => 'Nom du lieu',
                'attr' => [
                    'placeholder' => 'Indiquez le nom du lieu',
                ],
            ])
            ->add('placeStreet', TextType::class, [
                'label' => 'Rue',
                'required' => false,
            ])
            ->add('latitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('placeCity', TextType::class, [
                'label' => 'Ville',
                'required' => false,
            ])
            ->add('placePostalCode', TextType::class, [
                'label' => 'Code Postal',
                'required' => false,
            ])
            ->add('placeCountry', EntityType::class, [
                'label' => 'Pays',
                'placeholder' => '?',
                'class' => Country::class,
                'query_builder' => fn (CountryRepository $er) => $er->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
                'choice_label' => 'name',
            ])
            ->add('websiteContacts', CollectionType::class, [
                'entry_type' => UrlType::class,
                'required' => false,
                'add_entry_label' => 'Ajouter un site',
                'label' => 'Sites de réservation',
                'entry_options' => [
                    'label' => false,
                    'block_prefix' => 'app_collection_entry_main_text',
                    'attr' => [
                        'placeholder' => 'https://monsupersite.fr',
                    ],
                ],
            ])
            ->add('phoneContacts', CollectionType::class, [
                'entry_type' => TextType::class,
                'required' => false,
                'add_entry_label' => 'Ajouter un numéro',
                'label' => 'Numéros de téléphone',
                'entry_options' => [
                    'label' => false,
                    'block_prefix' => 'app_collection_entry_main_text',
                    'attr' => [
                        'placeholder' => '06 01 02 03 04',
                    ],
                ],
            ])
            ->add('mailContacts', CollectionType::class, [
                'entry_type' => EmailType::class,
                'required' => false,
                'add_entry_label' => 'Ajouter un email',
                'label' => 'Emails de contact',
                'entry_options' => [
                    'label' => false,
                    'block_prefix' => 'app_collection_entry_main_text',
                    'attr' => [
                        'placeholder' => 'vousêtes@incroyable.fr',
                    ],
                ],
            ])
            ->addEventListener(FormEvents::SUBMIT, [$this, 'onSubmit']);

        if (null !== $options['data'] && null === $options['data']->getId()) {
            $builder
                ->add('comment', TextareaType::class, [
                    'label' => 'Commentaire',
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'rows' => 8,
                        'placeholder' => 'Laisser un commentaire qui sera visible par les internautes',
                    ],
                ]);
        }

        $builder->get('latitude')->addModelTransformer(new CallbackTransformer(
            fn ($latitude) => (float) $latitude ?: null,
            fn ($latitude) => (float) $latitude ?: null
        ));

        $builder->get('longitude')->addModelTransformer(new CallbackTransformer(
            fn ($latitude) => (float) $latitude ?: null,
            fn ($latitude) => (float) $latitude ?: null
        ));
    }

    public function onSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if (!$data) {
            return;
        }

        $data = $this->doctrineEventHandler->handleOne($data, false);
        $event->setData($data);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }

    public function getName()
    {
        return 'app_event';
    }
}
