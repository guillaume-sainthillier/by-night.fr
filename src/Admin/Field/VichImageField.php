<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Admin\Field;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\FieldTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * EasyAdmin field backed by VichUploader (S3 storage + Picasso thumbnails), so that
 * back-office uploads follow the exact same pipeline as front-office ones.
 *
 * The property must be the Vich "file" property (e.g. `imageFile`), not the embedded
 * metadata one. The index/detail template reads the entity itself because the file
 * property is never hydrated (`inject_on_load: false`).
 */
final class VichImageField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        return new self()
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setTemplatePath('admin/field/vich_image.html.twig')
            ->setFormType(VichImageType::class)
            ->setFormTypeOptions([
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
                // Required by App\Form\Extension\ImageTypeExtension; the admin form theme
                // renders its own preview so only the presence of the option matters.
                'thumb_params' => ['w' => 300],
            ])
            ->addCssClass('field-image')
            ->setDefaultColumns('col-md-7 col-xxl-5');
    }
}
