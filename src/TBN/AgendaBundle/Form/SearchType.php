<?php

namespace TBN\AgendaBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class SearchType extends AbstractType
{

    protected $types_manifesation;
    protected $lieux;
    protected $commune;

    public function __construct($types_manifesation, $lieux, $commune)
    {
        $this->types_manifesation = $types_manifesation;
        $this->lieux = $lieux;
        $this->commune = $commune;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("du", "date", [
                "required" => true,
                "label" => "Du",
                'label_attr' => array('class' => 'col-sm-4 control-label'),
                "widget" => "single_text",
                "format" => "dd/MM/yyyy",
                "attr" => ["class" => "datepicker floating-labe", "data-date-format" => "dd/mm/yyyy"]
            ])
            ->add("au", "date", [
                "required" => false,
                "label"     => "Au",
                'label_attr' => array('class' => 'col-sm-4 control-label'),
                "widget" => "single_text",
                "format" => "dd/MM/yyyy",
                "attr" => ["class" => "datepicker", "data-date-format" => "dd/mm/yyyy"]
            ])
            ->add("type_manifestation", "choice", [
                "choices"  => $this->types_manifesation,
                "label"    => "Types d'événement",
                'label_attr' => array('class' => 'col-sm-5 control-label'),
                "multiple" => true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Tous", "class" => "form-control", "data-style" => "btn-primary btn-flat", "data-live-search" => true]])
             ->add("lieux", "choice", [
                "choices"  => $this->lieux,
                "label"    => "Lieux",
                "multiple" =>  true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Tous", "class" => "form-control", "data-style" => "btn-primary btn-flat", "data-live-search" => true]])
             ->add("commune", "choice", [
                "choices"  => $this->commune,
                "label"    => "Villes",
                "multiple" =>  true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Toutes", "class" => "form-control", "data-style" => "btn-primary btn-flat", "data-live-search" => true]])
            ->add('term', 'text', [
                "required" => false,
                "label"    => "Recherche par mot-clés",
                "attr" => ["class" => "form-control","placeholder" => "Que cherchez-vous ?"]])
            ->add('chercher', 'submit', ["label" => "Go !", "attr" => ["class" => "btn btn-lg btn-primary btn-block"]])
        ;
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'TBN\AgendaBundle\Search\SearchAgenda'
        ]);
    }

    public function getName()
    {
        return 'tbn_search_agenda';
    }
}
