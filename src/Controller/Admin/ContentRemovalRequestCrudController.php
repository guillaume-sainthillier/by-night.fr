<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Admin;

use App\Entity\ContentRemovalRequest;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\ContentRemovalRequestStatus;
use App\Enum\ContentRemovalType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute(path: '/content-removal-request', name: 'content_removal_request')]
final class ContentRemovalRequestCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContentRemovalRequest::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Demande de suppression')
            ->setEntityLabelInPlural('Demandes de suppression')
            ->setSearchFields(['id', 'email', 'message'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(ContentRemovalRequestStatus::cases()))
            ->add(ChoiceFilter::new('type')->setChoices(ContentRemovalType::cases()))
            ->add(EntityFilter::new('event'));
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $removeImage = Action::new('removeImage', 'Supprimer l\'image', 'lucide:image-minus')
            ->linkToCrudAction('removeImage')
            ->displayIf(static fn (ContentRemovalRequest $entity): bool => ContentRemovalRequestStatus::Pending === $entity->getStatus() && ContentRemovalType::Image === $entity->getType())
            ->addCssClass('btn btn-warning');

        $removeEvent = Action::new('removeEvent', 'Supprimer l\'événement', 'lucide:trash-2')
            ->linkToCrudAction('removeEvent')
            ->displayIf(static fn (ContentRemovalRequest $entity): bool => ContentRemovalRequestStatus::Pending === $entity->getStatus() && ContentRemovalType::Event === $entity->getType())
            ->addCssClass('btn btn-danger');

        $markAsProcessed = Action::new('markAsProcessed', 'Marquer traité', 'lucide:check')
            ->linkToCrudAction('markAsProcessed')
            ->displayIf(static fn (ContentRemovalRequest $entity): bool => ContentRemovalRequestStatus::Pending === $entity->getStatus())
            ->addCssClass('btn btn-success');

        $reject = Action::new('reject', 'Rejeter', 'lucide:x')
            ->linkToCrudAction('reject')
            ->displayIf(static fn (ContentRemovalRequest $entity): bool => ContentRemovalRequestStatus::Pending === $entity->getStatus())
            ->addCssClass('btn btn-secondary');

        $batchMarkAsProcessed = Action::new('batchMarkAsProcessed', 'Marquer traité', 'lucide:check')
            ->linkToCrudAction('batchMarkAsProcessed')
            ->addCssClass('btn btn-success');

        $batchReject = Action::new('batchReject', 'Rejeter', 'lucide:x')
            ->linkToCrudAction('batchReject')
            ->addCssClass('btn btn-secondary');

        $batchRemoveImages = Action::new('batchRemoveImages', 'Supprimer les images', 'lucide:image-minus')
            ->linkToCrudAction('batchRemoveImages')
            ->addCssClass('btn btn-warning');

        $batchRemoveEvents = Action::new('batchRemoveEvents', 'Supprimer les événements', 'lucide:trash-2')
            ->linkToCrudAction('batchRemoveEvents')
            ->addCssClass('btn btn-danger');

        return parent::configureActions($actions)
            ->disable(Action::NEW)
            ->add(Crud::PAGE_DETAIL, $removeImage)
            ->add(Crud::PAGE_DETAIL, $removeEvent)
            ->add(Crud::PAGE_DETAIL, $markAsProcessed)
            ->add(Crud::PAGE_DETAIL, $reject)
            ->add(Crud::PAGE_INDEX, $removeImage)
            ->add(Crud::PAGE_INDEX, $removeEvent)
            ->add(Crud::PAGE_INDEX, $markAsProcessed)
            ->add(Crud::PAGE_INDEX, $reject)
            ->addBatchAction($batchMarkAsProcessed)
            ->addBatchAction($batchReject)
            ->addBatchAction($batchRemoveImages)
            ->addBatchAction($batchRemoveEvents);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $panel1 = FormField::addPanel('Demande');
        $id = IdField::new('id', 'ID');
        $email = EmailField::new('email');
        $type = ChoiceField::new('type')
            ->setChoices(ContentRemovalType::cases())
            ->renderAsBadges([
                ContentRemovalType::Image->value => 'warning',
                ContentRemovalType::Event->value => 'danger',
            ]);
        $message = TextareaField::new('message');
        $eventUrls = ArrayField::new('eventUrls', 'URLs');
        $event = AssociationField::new('event')
            ->setCrudController(EventCrudController::class)
            ->autocomplete();
        $createdAt = DateTimeField::new('createdAt');

        $panel2 = FormField::addPanel('Traitement');
        $status = ChoiceField::new('status')
            ->setChoices(ContentRemovalRequestStatus::cases())
            ->renderAsBadges([
                ContentRemovalRequestStatus::Pending->value => 'warning',
                ContentRemovalRequestStatus::Processed->value => 'success',
                ContentRemovalRequestStatus::Rejected->value => 'danger',
            ]);
        $processedAt = DateTimeField::new('processedAt');
        $processedBy = AssociationField::new('processedBy')
            ->setCrudController(UserCrudController::class)
            ->autocomplete();

        if (Crud::PAGE_INDEX === $pageName) {
            return [$id, $createdAt, $email, $type, $status, $event];
        }

        return [
            $panel1,
            $id->hideOnForm(),
            $createdAt->hideOnForm(),
            $email,
            $type,
            $message,
            $eventUrls,
            $event,

            $panel2,
            $status,
            $processedAt->hideOnForm(),
            $processedBy->hideOnForm(),
        ];
    }

    public function removeImage(AdminContext $context): Response
    {
        /** @var ContentRemovalRequest $request */
        $request = $context->getEntity()->getInstance();

        $this->removeEventImage($request->getEvent());
        $this->markRequestAsProcessed($request);

        $this->addFlash('success', 'L\'image de l\'événement a été supprimée.');

        return $this->redirectToDetailPage($request);
    }

    public function removeEvent(AdminContext $context): Response
    {
        /** @var ContentRemovalRequest $request */
        $request = $context->getEntity()->getInstance();
        $event = $request->getEvent();

        if (null !== $event) {
            $this->entityManager->remove($event);
        }

        $this->markRequestAsProcessed($request);

        $this->addFlash('success', 'L\'événement a été supprimé.');

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function markAsProcessed(AdminContext $context): Response
    {
        /** @var ContentRemovalRequest $request */
        $request = $context->getEntity()->getInstance();

        $this->markRequestAsProcessed($request);

        $this->addFlash('success', 'La demande a été marquée comme traitée.');

        return $this->redirectToDetailPage($request);
    }

    public function reject(AdminContext $context): Response
    {
        /** @var ContentRemovalRequest $request */
        $request = $context->getEntity()->getInstance();

        $this->markRequestAsRejected($request);

        $this->addFlash('success', 'La demande a été rejetée.');

        return $this->redirectToDetailPage($request);
    }

    public function batchMarkAsProcessed(BatchActionDto $batchActionDto): Response
    {
        $count = 0;
        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var ContentRemovalRequest|null $request */
            $request = $this->entityManager->find(ContentRemovalRequest::class, $id);
            if (null !== $request && ContentRemovalRequestStatus::Pending === $request->getStatus()) {
                $this->markRequestAsProcessed($request, false);
                ++$count;
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('%d demande(s) marquée(s) comme traitée(s).', $count));

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    public function batchReject(BatchActionDto $batchActionDto): Response
    {
        $count = 0;
        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var ContentRemovalRequest|null $request */
            $request = $this->entityManager->find(ContentRemovalRequest::class, $id);
            if (null !== $request && ContentRemovalRequestStatus::Pending === $request->getStatus()) {
                $this->markRequestAsRejected($request, false);
                ++$count;
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('%d demande(s) rejetée(s).', $count));

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    public function batchRemoveImages(BatchActionDto $batchActionDto): Response
    {
        $count = 0;
        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var ContentRemovalRequest|null $request */
            $request = $this->entityManager->find(ContentRemovalRequest::class, $id);
            if (null !== $request
                && ContentRemovalRequestStatus::Pending === $request->getStatus()
                && ContentRemovalType::Image === $request->getType()
            ) {
                $this->removeEventImage($request->getEvent());
                $this->markRequestAsProcessed($request, false);
                ++$count;
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('%d image(s) supprimée(s).', $count));

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    public function batchRemoveEvents(BatchActionDto $batchActionDto): Response
    {
        $count = 0;
        foreach ($batchActionDto->getEntityIds() as $id) {
            /** @var ContentRemovalRequest|null $request */
            $request = $this->entityManager->find(ContentRemovalRequest::class, $id);
            if (null !== $request
                && ContentRemovalRequestStatus::Pending === $request->getStatus()
                && ContentRemovalType::Event === $request->getType()
            ) {
                $event = $request->getEvent();
                if (null !== $event) {
                    $this->entityManager->remove($event);
                }
                $this->markRequestAsProcessed($request, false);
                ++$count;
            }
        }

        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('%d événement(s) supprimé(s).', $count));

        return $this->redirect($batchActionDto->getReferrerUrl());
    }

    private function removeEventImage(?Event $event): void
    {
        if (null === $event) {
            return;
        }

        $event->setImageFile(null);
        $event->setImageHash(null);
        $event->setImageSystemFile(null);
        $event->setImageSystemHash(null);
    }

    private function markRequestAsProcessed(ContentRemovalRequest $request, bool $flush = true): void
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $request->setStatus(ContentRemovalRequestStatus::Processed);
        $request->setProcessedAt(new DateTimeImmutable());
        $request->setProcessedBy($user);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    private function markRequestAsRejected(ContentRemovalRequest $request, bool $flush = true): void
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $request->setStatus(ContentRemovalRequestStatus::Rejected);
        $request->setProcessedAt(new DateTimeImmutable());
        $request->setProcessedBy($user);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    private function redirectToDetailPage(ContentRemovalRequest $request): Response
    {
        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($request->getId())
            ->generateUrl());
    }
}
