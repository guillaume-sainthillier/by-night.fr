<?php

namespace TBN\UserBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use TBN\MajDataBundle\Handler\DoctrineEventHandler;
use Vich\UploaderBundle\Form\Type\VichImageType;

class AgendaType extends AbstractType
{
    /**
     * @var \TBN\MajDataBundle\Handler\DoctrineEventHandler
     */
    private $doctrineEventHandler;

    public function __construct(DoctrineEventHandler $doctrineEventHandler)
    {
        $this->doctrineEventHandler = $doctrineEventHandler;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $siteInfo = $options['site_info'];
        $user = $options['user'];
        $configs = $options['config'];

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Titre',
                'attr'  => [
                    'placeholder' => 'Choisissez un titre accrocheur...',
                ],
            ])
            ->add('descriptif', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Décrivez votre événement...',
                ],
            ])
            ->add('file', VichImageType::class, [
                'label'        => 'Affiche / Flyer',
                'required'     => false,
                'image_filter' => 'thumb_evenement',
            ])
            ->add('dateDebut', DateType::class, [
                'label'    => 'A partir du ',
                'widget'   => 'single_text',
                'required' => true,
                'format'   => 'dd/MM/yyyy',
                'attr'     => [
                    'placeholder' => 'Le / Du...',
                ],
            ])
            ->add('dateFin', DateType::class, [
                'label'    => "Jusqu'au",
                'widget'   => 'single_text',
                'required' => false,
                'format'   => 'dd/MM/yyyy',
                'attr'     => [
                    'placeholder' => 'Au...',
                ],
            ])
            ->add('horaires', TextType::class, [
                'label'    => 'Horaires',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'A 20h, de 21h à minuit',
                ],
            ]);

        foreach ($configs as $service => $config) {
            $nomService = $config['nom'];
            $accessService = ucfirst($service);
            if ($nomService === '') {
                $nomService = $accessService;
            }

            $getter = 'get'.$accessService.'AccessToken';
            $is_api_ready = ($config['enabled'] && $siteInfo && $siteInfo->$getter());

            if (!$is_api_ready) {
                $message = "L'accès à ".$nomService.' est momentanément désactivé';
                $post_checked = false;
                $post_disabled = true;
            } else {
                if ($service === 'facebook') {
                    $role = 'ROLE_FACEBOOK_EVENTS';
                } else {
                    $role = 'ROLE_'.strtoupper($service);
                }
                $post_disabled = false;
                $post_checked = $user->hasRole($role);

                if ($post_checked) {
                    $info = $user->getInfo();
                    $getter = 'get'.$accessService.'Nickname';
                    $message = 'Connecté sous '.($service === 'twitter' ? '@' : '').$info->$getter();
                } else {
                    $message = 'Connectez vous à '.$nomService;
                }
            }

            $builder->add('share_'.$service, CheckboxType::class, [
                'label'    => 'Poster mon événement sur '.$nomService,
                'required' => false,
                'mapped'   => false,
                'disabled' => $post_disabled,
                'data'     => $post_checked,
                'attr'     => [
                    'class'          => 'social_post onoffswitch-checkbox '.($post_checked ? 'checked' : ''),
                    'data-connected' => (!$post_disabled && $post_checked) ? '1' : '0',
                    'data-message'   => $message,
                ],
            ]);
        }

        $builder->add('tarif', TextType::class, [
            'label'    => 'Tarif',
            'required' => false,
            'attr'     => [
                'placeholder' => '17€ avec préventes, 20€ sur place',
            ],
        ])
        ->add('categorieManifestation', TextType::class, [
            'label'    => 'Catégorie',
            'required' => false,
            'attr'     => [
                'placeholder' => 'Concert, Spectacle, ...',
            ],
        ])
        ->add('themeManifestation', TextType::class, [
            'label'    => 'Thèmes',
            'required' => false,
            'attr'     => [
                'placeholder' => 'Humour, Tragédie, Jazz, Rock, Rap, ...',
            ],
        ])
        ->add('adresse', TextType::class, [
            'required' => false,
            'label'    => 'Adresse',
            'attr'     => [
                'placeholder' => 'Tapez votre adresse ici pour remplir les champs ci-dessous',
            ],
        ])
        ->add('place', PlaceType::class, [
            'label' => false,
        ])
        ->add('reservationInternet', UrlType::class, [
            'label'    => 'Réservation par internet',
            'required' => false,
            'attr'     => [
                'placeholder' => "L'URL où trouver un billet",
            ],
        ])
        ->add('reservationTelephone', TextType::class, [
            'label'    => 'Réservation téléphonique',
            'required' => false,
            'attr'     => [
                'placeholder' => 'Le numéro à appeler pour acheter un billet',
            ],
        ])
        ->add('reservationEmail', EmailType::class, [
            'label'    => 'Réservation par mail',
            'required' => false,
            'attr'     => [
                'placeholder' => 'Le mail pour vous contacter',
            ],
        ])
        ->addEventListener(FormEvents::SUBMIT, [$this, 'onSubmit']);
    }

    public function onSubmit(FormEvent $event)
    {
        $data = $event->getData();

        if (!$data) {
            return;
        }

        $evenement = $this->doctrineEventHandler->handleOne($data);
        $event->setData($evenement);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\AgendaBundle\Entity\Agenda',
            'site_info'  => null,
            'user'       => null,
            'config'     => [],
        ]);
    }

    public function getName()
    {
        return 'tbn_agenda';
    }
}
