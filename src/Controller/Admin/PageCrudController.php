<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Admin\Field\VichImageField;
use App\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;

#[AdminRoute(path: '/page', name: 'page')]
final class PageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Page')
            ->setEntityLabelInPlural('Pages')
            ->setSearchFields([
                'id',
                'title',
                'slug',
                'content',
            ])
            ->setDefaultSort(['updatedAt' => 'DESC']);
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        // linkToUrl() receives the entity on index/detail/edit: never add it as a global or NEW action
        $viewPage = Action::new('viewPage', 'Voir la page', 'lucide:eye')
            ->linkToUrl(fn (Page $page): string => $this->generateUrl('app_page_show', ['slug' => (string) $page->getSlug()]))
            ->setHtmlAttributes(['target' => '_blank', 'rel' => 'noopener']);

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $viewPage)
            ->add(Crud::PAGE_DETAIL, $viewPage)
            ->add(Crud::PAGE_EDIT, $viewPage);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $contentPanel = FormField::addFieldset('Contenu');
        $id = IdField::new('id', 'ID');
        $title = TextField::new('title', 'Titre');
        // required=false is mandatory: EasyAdmin derives "required" from the NOT NULL column, and the
        // HTML attribute would prevent clearing the slug to let Gedmo regenerate it from the title.
        $slug = SlugField::new('slug', 'Slug')
            ->setTargetFieldName('title')
            ->setRequired(false)
            ->setHelp('URL publique : <code>/p/{slug}</code>. Laissez vide pour le générer depuis le titre.');
        $image = VichImageField::new('imageFile', 'Image');
        $content = CodeEditorField::new('content', 'Contenu HTML')
            ->setLanguage('xml')
            ->setNumOfRows(30);

        $seoPanel = FormField::addFieldset('SEO');
        $metaTitle = TextField::new('metaTitle', 'Meta title')
            ->setHelp('≈ 60 caractères. Remplace le titre dans la balise <code>&lt;title&gt;</code>.');
        $metaDescription = TextareaField::new('metaDescription', 'Meta description')
            ->setNumOfRows(3)
            ->setHelp('≈ 155 caractères.');

        $createdAt = DateTimeField::new('createdAt', 'Créée le');
        $updatedAt = DateTimeField::new('updatedAt', 'Modifiée le');

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $image, $title, $slug, $updatedAt];
        }

        return [
            $contentPanel,
            $id->hideOnForm(),
            $title,
            $slug,
            $image,
            $content,
            $seoPanel,
            $metaTitle,
            $metaDescription,
            $createdAt->hideOnForm(),
            $updatedAt->hideOnForm(),
        ];
    }
}
