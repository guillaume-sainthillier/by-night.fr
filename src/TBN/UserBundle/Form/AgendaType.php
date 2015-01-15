<?php

namespace TBN\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use TBN\UserBundle\Entity\SiteInfo;
use TBN\UserBundle\Entity\User;

class AgendaType extends AbstractType
{

    /**
     *
     * @var SiteInfo
     */
    protected $siteInfo;

    /**
     *
     * @var UserInfo
     */
    protected $userInfo;

    /**
     *
     * @var User
     */
    protected $user;

    protected $options;


    public function __construct(SiteInfo $siteInfo, User $user, array $options)
    {
        $this->siteInfo = $siteInfo;
        $this->user     = $user;
        $this->userInfo = $user->getInfo();
        $this->options  = $options;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom','text',[
                "label" => "Titre",
                "attr" => [
                    "placeholder" => "Choisissez un titre accrocheur..."
                ]
            ])
            ->add('descriptif','textarea',[
                "label" => "Description",
                "required" => false,
                "attr" => [
                    "placeholder" => "Décrivez votre événement...",
                ]
            ])
            ->add('file','file', [
                "label" => "Affiche / Flyer",
                "required" => false,
                'image_path' => 'webPath'
            ])
            ->add('dateDebut','date', [
                "label" => "A partir du ",
                'widget' => 'single_text',
                "required" => true,
                "format" => "dd/MM/yyyy",
                "attr" => [
                    "placeholder" => "Le / Du...",
                    "class" => "datepicker"
                ]
            ])
            ->add('dateFin','date', [
                "label" => "Jusqu'au",
                'widget' => 'single_text',
                "required" => false,
                "format" => "dd/MM/yyyy",
                "attr" => [
                    "placeholder" => "Au...",
                    "class" => "datepicker"
                ]
            ])
            ->add('horaires','text', [
                "label" => "Horaires",
                "required" => false,
                "attr" => [
                    "placeholder" => "A 20h, de 21h à minuit"
                ]
            ]);

        foreach($this->options as $service => $config)
        {
            $nomService = $config["nom"];
            $accessService = ucfirst($service);
            if($nomService === '')
            {
                $nomService = $accessService;
            }

            $getter = "get".$accessService."AccessToken";
            $is_api_ready = ($config["enabled"] and $this->siteInfo and $this->siteInfo->$getter());
            
            if(! $is_api_ready)
            {
                $message = "L'accès à ".$nomService." est momentanément désactivé";
                $post_checked = false;
                $post_disabled = true;
            }else
            {
                if($service === "facebook")
                {
                    $role = "ROLE_FACEBOOK_EVENTS";
                }else
                {
                    $role = "ROLE_".strtoupper($service);
                }
                $post_disabled = false;
                $post_checked = $this->user->hasRole($role);

                if($post_checked)
                {
                    $info = $this->user->getInfo();
                    $getter = "get".$accessService."Nickname";
                    $message = "Connecté sous ".($service === 'twitter' ? '@': '').$info->$getter();
                }else
                {
                    $message = "Connectez vous à ".$nomService;
                }
            }

            $builder->add('share_' .$service,'checkbox', [
                "label" => "Poster mon événement sur " .$nomService,
                "required" => false,
                "mapped" => false,
                "disabled" => $post_disabled,
                "data" => $post_checked,
                "attr" => array(
                    "class" => "social_post onoffswitch-checkbox ".($post_checked ? "checked" : ""),
                    "data-connected" => (!$post_disabled and $post_checked) ? "1" : "0",
                    "data-message" => $message
                )
            ]);
        }

            $builder->add('tarif','text', [
                "label" => "Tarif",
                "required" => false,
                "attr" => [
                    "placeholder" => "17€ avec préventes, 20€ sur place"
                ]
            ])
            ->add('categorieManifestation','text', [
                "label" => "Catégorie",
                "required" => false,
                "attr" => [
                    "placeholder" => "Concert, Spectacle, ..."
                ]
            ])
            ->add('themeManifestation','text', [
                "label" => "Thèmes",
                "required" => false,
                "attr" => [
                    "placeholder" => "Humour, Tragédie, Jazz, Rock, Rap, ..."
                ]
            ])
            ->add('adresse', 'text', [
                "required" => false,
                "label" => "Adresse",
                "attr" => [
                    "placeholder" => "Tapez votre adresse ici pour remplir les champs ci-dessous"
                ]
            ])
            ->add('place', new PlaceType, [
                "label" => false,
            ])
            ->add('reservationInternet','url', [
                "label" => "Réservation par internet",
                "required" => false,
                "attr" => [
                    "placeholder" => "L'URL où trouver un billet"
                ]
            ])
            ->add('reservationTelephone','text', [
                "label" => "Réservation téléphonique",
                "required" => false,
                "attr" => [
                    "placeholder" => "Le numéro à appeler pour acheter un billet"
                ]
            ])
            ->add('reservationEmail','email', [
                "label" => "Réservation par mail",
                "required" => false,
                "attr" => [
                    "placeholder" => "Le mail pour vous contacter"
                ]
            ])
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\AgendaBundle\Entity\Agenda'
        ]);
    }

    public function getName()
    {
        return 'tbn_agenda';
    }
}
