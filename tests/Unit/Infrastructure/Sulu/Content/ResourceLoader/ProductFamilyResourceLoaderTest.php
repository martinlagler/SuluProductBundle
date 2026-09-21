<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\ResourceLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductFamilyResourceLoader;

#[CoversClass(ProductFamilyResourceLoader::class)]
class ProductFamilyResourceLoaderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

    private ProductFamilyResourceLoader $loader;

    public function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->loader = new ProductFamilyResourceLoader($this->productFamilyRepository->reveal());
    }

    public function testGetKey(): void
    {
        $this->assertSame('product_family', ProductFamilyResourceLoader::getKey());
    }

    public function testLoadMapsFamiliesByUuidInTheRequestedLocale(): void
    {
        $speakon = $this->createFamily('uuid-1', 'SPK', ['en' => 'speakON', 'de' => 'speakON DE']);
        $ethercon = $this->createFamily('uuid-2', null, ['de' => 'etherCON']);

        $this->productFamilyRepository->findBy(['uuids' => ['uuid-1', 'uuid-2']])
            ->willReturn([$speakon, $ethercon])
            ->shouldBeCalledOnce();

        $this->assertSame([
            'uuid-1' => ['uuid' => 'uuid-1', 'externalIdentifier' => 'SPK', 'name' => 'speakON'],
            'uuid-2' => ['uuid' => 'uuid-2', 'externalIdentifier' => null, 'name' => null],
        ], $this->loader->load(['uuid-1', 'uuid-2'], 'en'));
    }

    public function testLoadSkipsAFamilyWithoutUuid(): void
    {
        $this->productFamilyRepository->findBy(['uuids' => ['uuid-1']])
            ->willReturn([new ProductFamily()]);

        $this->assertSame([], $this->loader->load(['uuid-1'], 'en'));
    }

    public function testLoadWithoutLocaleQueriesNothing(): void
    {
        $this->productFamilyRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $this->assertSame([], $this->loader->load(['uuid-1'], null));
    }

    public function testLoadWithoutIdsQueriesNothing(): void
    {
        $this->productFamilyRepository->findBy(Argument::cetera())->shouldNotBeCalled();

        $this->assertSame([], $this->loader->load([], 'en'));
    }

    /**
     * @param array<string, string> $names
     */
    private function createFamily(string $uuid, ?string $externalIdentifier, array $names): ProductFamily
    {
        $family = new ProductFamily();
        $family->setUuid($uuid);
        $family->setExternalIdentifier($externalIdentifier);
        foreach ($names as $locale => $name) {
            $family->addTranslation(new ProductFamilyTranslation($family, $locale, $name));
        }

        return $family;
    }
}
