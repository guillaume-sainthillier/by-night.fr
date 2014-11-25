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
                "required" => false,
                "widget" => "single_text",
                "format" => "d/M/y",
                "attr" => ["class" => "datepicker", "data-date-format" => "dd/mm/yyyy"]
            ])
            ->add("au", "date", [
                "required" => false,
                "widget" => "single_text",
                "format" => "d/M/y",
                "attr" => ["class" => "datepicker", "data-date-format" => "dd/mm/yyyy"]
            ])
            ->add("type_manifestation", "choice", [
                "choices"  => $this->types_manifesation,
                "multiple" => true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Type d'événement", "data-style" => "btn-warning", "data-live-search" => true]])
             ->add("lieux", "choice", [
                "choices"  => $this->lieux,
                "multiple" =>  true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Lieux", "data-style" => "btn-warning", "data-live-search" => true]])
             ->add("commune", "choice", [
                "choices"  => $this->commune,
                "multiple" =>  true,
                "expanded" => false,
                "required" => false,
                "attr" => ["title" => "Villes", "data-style" => "btn-warning", "data-live-search" => true]])
            ->add('term', 'text', [
                "required" => false,
                "attr" => ["class" => "form-control","placeholder" => "Recherchez un événement"]])
            ->add('chercher', 'submit', ["label" => "Go!", "attr" => ["class" => "btn btn-info btn-normal"]])
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
