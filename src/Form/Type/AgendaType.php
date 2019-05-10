<?php

namespace App\Form\Type;

use App\Entity\Agenda;
use App\Entity\Country;
use App\Handler\DoctrineEventHandler;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class AgendaType extends AbstractType
{
    /**
     * @var DoctrineEventHandler
     */
    private $doctrineEventHandler;

    public function __construct(DoctrineEventHandler $doctrineEventHandler)
    {
        $this->doctrineEventHandler = $doctrineEventHandler;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
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
            ->add('file', VichImageType::class, [
                'label' => 'Affiche / Flyer',
                'required' => false,
                'image_filter' => 'thumb_evenement',
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'A partir du ',
                'widget' => 'single_text',
                'required' => true,
                'format' => 'dd/MM/yyyy',
                'attr' => [
                    'placeholder' => 'Le / Du...',
                ],
            ])
            ->add('dateFin', DateType::class, [
                'label' => "Jusqu'au",
                'widget' => 'single_text',
                'required' => false,
                'format' => 'dd/MM/yyyy',
                'attr' => [
                    'placeholder' => 'Au...',
                ],
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
                'required' => false,
            ])
            ->add('placeCountry', EntityType::class, [
                'label' => 'Pays',
                'placeholder' => '?',
                'class' => Country::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('c')
                        ->orderBy('c.name', 'ASC');
                },
                'choice_label' => 'name',
            ])
            ->add('reservationInternet', UrlType::class, [
                'label' => 'Réservation par internet',
                'required' => false,
                'attr' => [
                    'placeholder' => "L'URL où trouver un billet",
                ],
            ])
            ->add('reservationTelephone', TextType::class, [
                'label' => 'Réservation téléphonique',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Le numéro à appeler pour acheter un billet',
                ],
            ])
            ->add('reservationEmail', EmailType::class, [
                'label' => 'Réservation par mail',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Le mail pour vous contacter',
                ],
            ])
            ->addEventListener(FormEvents::SUBMIT, [$this, 'onSubmit']);

        $builder->get('latitude')->addModelTransformer(new CallbackTransformer(
            function ($latitude) {
                return (float)$latitude ?: null;
            },
            function ($latitude) {
                return (float)$latitude ?: null;
            }
        ));

        $builder->get('longitude')->addModelTransformer(new CallbackTransformer(
            function ($latitude) {
                return (float)$latitude ?: null;
            },
            function ($latitude) {
                return (float)$latitude ?: null;
            }
        ));
    }

    public function onSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if (!$data) {
            return;
        }

        $this->doctrineEventHandler->handleOne($data, false);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Agenda::class,
        ]);
    }

    public function getName()
    {
        return 'app_agenda';
    }
}
