<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\ParserState;
use DateTimeImmutable;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Symfony\Component\Validator\Constraint;

#[AdminRoute(path: '/parser-state', name: 'parser_state')]
final class ParserStateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ParserState::class;
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        // app:events:import writes the row on the parser's first successful run
        return parent::configureActions($actions)
            ->disable(Action::NEW)
        ;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Point de reprise')
            ->setEntityLabelInPlural('Points de reprise')
            ->setSearchFields(['parser'])
            ->setDefaultSort(['parser' => 'ASC']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        // Read before the form is submitted, so this is the watermark as stored
        $state = $this->getContext()?->getEntity()->getInstance();

        $id = IdField::new('id', 'ID');
        // The parser is the row's identity (no setter): shown on the form for context only
        $parser = TextField::new('parser', 'Parser')->setDisabled();
        $lastParsedAt = DateTimeField::new('lastParsedAt', 'Début du dernier import réussi')
            ->setHelp('Au prochain passage, un parser incrémental récupère les changements de sa source depuis cette date, moins une heure de marge. Supprimer la ligne relance un import complet.')
            ->setFormTypeOption('constraints', $this->getLastParsedAtConstraints($state instanceof ParserState ? $state->getLastParsedAt() : null));

        return [
            $id->hideOnForm(),
            $parser,
            $lastParsedAt,
        ];
    }

    /**
     * Guards the edit form. The next incremental run fetches what its source changed since the
     * new date, then moves the watermark to its own start: moving it back only re-fetches a
     * window the dedup gate absorbs, moving it forward skips the changes in between for good.
     *
     * @param DateTimeImmutable|null $storedLastParsedAt the watermark as stored, null outside the edit page
     *
     * @return Constraint[]
     */
    private function getLastParsedAtConstraints(?DateTimeImmutable $storedLastParsedAt): array
    {
        // TODO: decide how far the admin may move the watermark (see the docblock)
        return [];
    }
}
