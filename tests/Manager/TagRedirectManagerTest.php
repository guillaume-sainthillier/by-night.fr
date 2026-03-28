<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Manager;

use App\Entity\Tag;
use App\Exception\RedirectException;
use App\Manager\TagRedirectManager;
use App\Repository\TagRepository;
use Override;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TagRedirectManagerTest extends KernelTestCase
{
    private TagRepository&Stub $tagRepository;

    private RequestStack&Stub $requestStack;

    private UrlGeneratorInterface&Stub $urlGenerator;

    private TagRedirectManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->tagRepository = $this->createStub(TagRepository::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->urlGenerator = $this->createStub(UrlGeneratorInterface::class);

        $this->manager = new TagRedirectManager(
            $this->requestStack,
            $this->urlGenerator,
            $this->tagRepository
        );
    }

    public function testGetTagByIdReturnsTag(): void
    {
        $tag = $this->createTag(123, 'concert', 'Concert');

        $this->tagRepository
            ->method('find')
            ->willReturn($tag);

        $this->requestStack
            ->method('getParentRequest')
            ->willReturn(null);

        $result = $this->manager->getTag(123, 'concert', 'toulouse', 'app_agenda_by_tag');

        self::assertSame($tag, $result);
    }

    public function testGetTagByIdThrowsNotFoundWhenTagDoesNotExist(): void
    {
        $this->tagRepository
            ->method('find')
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Tag with id "999" not found');

        $this->manager->getTag(999, 'unknown', 'toulouse', 'app_agenda_by_tag');
    }

    public function testGetTagByIdRedirectsWhenSlugMismatch(): void
    {
        $tag = $this->createTag(123, 'concert', 'Concert');

        $this->tagRepository
            ->method('find')
            ->willReturn($tag);

        $this->requestStack
            ->method('getParentRequest')
            ->willReturn(null);

        $this->urlGenerator
            ->method('generate')
            ->willReturn('/toulouse/agenda/tag/concert--123');

        $this->expectException(RedirectException::class);

        $this->manager->getTag(123, 'wrong-slug', 'toulouse', 'app_agenda_by_tag');
    }

    public function testGetTagByLegacySlugFindsExactSlug(): void
    {
        $tag = $this->createTag(123, 'concert', 'Concert');

        $this->tagRepository
            ->method('findOneBySlug')
            ->willReturn($tag);

        $this->requestStack
            ->method('getParentRequest')
            ->willReturn(null);

        $this->urlGenerator
            ->method('generate')
            ->willReturn('/toulouse/agenda/tag/concert--123');

        $this->expectException(RedirectException::class);

        // Legacy route (no ID) should redirect to canonical URL
        $this->manager->getTag(null, 'concert', 'toulouse', 'app_agenda_by_tag');
    }

    public function testGetTagByLegacySlugSlugifiesInput(): void
    {
        $tag = $this->createTag(123, 'concert', 'Concert');

        // First call with "Concert" returns null (not found)
        // Second call with "concert" (slugified) returns the tag
        $this->tagRepository
            ->method('findOneBySlug')
            ->willReturnCallback(static fn (string $slug) => 'concert' === $slug ? $tag : null);

        $this->requestStack
            ->method('getParentRequest')
            ->willReturn(null);

        $this->urlGenerator
            ->method('generate')
            ->willReturn('/toulouse/agenda/tag/concert--123');

        $this->expectException(RedirectException::class);

        // Legacy URL with uppercase "Concert" should find "concert" after slugifying
        $this->manager->getTag(null, 'Concert', 'toulouse', 'app_agenda_by_tag');
    }

    public function testGetTagByLegacySlugFindsByName(): void
    {
        $tag = $this->createTag(123, 'musique-classique', 'Musique classique');

        // Slug lookups return null
        $this->tagRepository
            ->method('findOneBySlug')
            ->willReturn(null);

        // Name lookup finds the tag
        $this->tagRepository
            ->method('findOneByName')
            ->willReturn($tag);

        $this->requestStack
            ->method('getParentRequest')
            ->willReturn(null);

        $this->urlGenerator
            ->method('generate')
            ->willReturn('/toulouse/agenda/tag/musique-classique--123');

        $this->expectException(RedirectException::class);

        // Legacy URL with name (URL-decoded) should find tag by name
        $this->manager->getTag(null, 'Musique classique', 'toulouse', 'app_agenda_by_tag');
    }

    public function testGetTagByLegacySlugThrowsNotFoundWhenNotFound(): void
    {
        $this->tagRepository
            ->method('findOneBySlug')
            ->willReturn(null);

        $this->tagRepository
            ->method('findOneByName')
            ->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Tag with slug "unknown-tag" not found');

        $this->manager->getTag(null, 'unknown-tag', 'toulouse', 'app_agenda_by_tag');
    }

    public function testGetTagDoesNotRedirectInSubRequest(): void
    {
        $tag = $this->createTag(123, 'concert', 'Concert');

        $this->tagRepository
            ->method('find')
            ->willReturn($tag);

        // Simulate a sub-request (ESI, etc.)
        $this->requestStack
            ->method('getParentRequest')
            ->willReturn(new Request());

        // Even with wrong slug, no redirect in sub-request
        $result = $this->manager->getTag(123, 'wrong-slug', 'toulouse', 'app_agenda_by_tag');

        self::assertSame($tag, $result);
    }

    public function testGetTagWithAdditionalRouteParams(): void
    {
        $tag = $this->createTag(123, 'concert', 'Concert');

        $this->tagRepository
            ->method('find')
            ->willReturn($tag);

        $this->requestStack
            ->method('getParentRequest')
            ->willReturn(null);

        $this->urlGenerator
            ->method('generate')
            ->willReturn('/toulouse/agenda/tag/concert--123/2');

        $this->expectException(RedirectException::class);

        $this->manager->getTag(123, 'wrong-slug', 'toulouse', 'app_agenda_by_tag', ['page' => 2]);
    }

    public function testGetTagHandlesAccentedCharacters(): void
    {
        $tag = $this->createTag(456, 'theatre', 'Théâtre');

        // "Théâtre" slugified becomes "theatre"
        $this->tagRepository
            ->method('findOneBySlug')
            ->willReturnCallback(static fn (string $slug) => 'theatre' === $slug ? $tag : null);

        $this->requestStack
            ->method('getParentRequest')
            ->willReturn(null);

        $this->urlGenerator
            ->method('generate')
            ->willReturn('/toulouse/agenda/tag/theatre--456');

        $this->expectException(RedirectException::class);

        // Legacy URL with accented "Théâtre" should find "theatre"
        $this->manager->getTag(null, 'Théâtre', 'toulouse', 'app_agenda_by_tag');
    }

    private function createTag(int $id, string $slug, string $name): Tag
    {
        $tag = new Tag();
        $tag->setName($name);

        // Use reflection to set the ID since it's normally auto-generated
        $reflection = new ReflectionClass($tag);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($tag, $id);

        // Set slug via reflection since it's generated by Gedmo
        $slugProperty = $reflection->getProperty('slug');
        $slugProperty->setValue($tag, $slug);

        return $tag;
    }
}
