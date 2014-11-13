<?php

namespace TBN\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use TBN\UserBundle\Entity\Info;
use TBN\UserBundle\Entity\SiteInfo;

class AgendaType extends AbstractType
{

    /**
     *
     * @var Info
     */
    protected $siteInfo;

    /**
     *
     * @var Info
     */
    protected $userInfo;

    protected $options;


    public function __construct(SiteInfo $siteInfo, Info $userInfo, array $options)
    {
        $this->siteInfo = $siteInfo;
        $this->userInfo = $userInfo;
        $this->options = $options;
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
                "required" => false
            ])
            ->add('dateDebut','genemu_jquerydate', [
                "label" => "A partir du ",
                'widget' => 'single_text',
                "required" => true,
                "attr" => [
                    "placeholder" => "Le / Du..."
                ]
            ])
            ->add('dateFin','genemu_jquerydate', [
                "label" => "Jusqu'au",
                'widget' => 'single_text',
                "required" => false,
                "attr" => [
                    "placeholder" => "Au..."
                ]
            ]);


        foreach($this->options as $service => $config)
        {
            $getter = "get".ucfirst($service)."AccessToken";
            $is_api_ready = ($config["enabled"] and $this->siteInfo and $this->siteInfo->$getter());
            
            if(! $is_api_ready)
            {
                $post_checked = false;
                $post_disabled = true;
            }else
            {
                $post_disabled = false;
                $post_checked = $this->userInfo->$getter() !== null;
            }

            $builder->add('share_' .$service,'checkbox', [
                "label" => "Poster mon événement sur " .ucfirst($service),
                "required" => false,
                "mapped" => false,
                "disabled" => $post_disabled,
                "data" => $post_checked,
                "attr" => array(
                    "class" => "social_post onoffswitch-checkbox ".($post_checked ? "checked" : ""),
                    "data-connected" => (!$post_disabled and $post_checked) ? "1" : "0"
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
                "label" => "Thème",
                "required" => false,
                "attr" => [
                    "placeholder" => "Humour, Tragédie, Jazz, Rock, Rap, ..."
                ]
            ])
            ->add('lieuNom', 'text', [
                "required" => true,
                "label" => "Où ça ?",
                "attr" => [
                    "placeholder" => "Indiquez le nom du lieu"
                ]
            ])
            ->add('adresse', 'text', [
                "required" => true,
                "label" => "Adresse",
                "attr" => [
                    "placeholder" => "Indiquez l'adresse exacte"
                ]
            ])
            ->add('latitude','hidden', [
                "required" => false,
            ])
            ->add('rue','hidden', [
                "required" => false,
            ])
            ->add('longitude','hidden', [
                "required" => false,
            ])
            ->add('codePostal','hidden', [
                "required" => false,
            ])
            ->add('ville','hidden', [
                "required" => false,
            ])
            ->add('reservationInternet','text', [
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
