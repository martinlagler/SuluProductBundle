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

namespace Sulu\Product\Tests\Functional\Content;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Content\Application\PropertyResolver\PropertyResolverProviderInterface;
use Sulu\Content\Application\ResourceLoader\ResourceLoaderProvider;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\ProductFamilySelectionPropertyResolver;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\SingleProductFamilySelectionPropertyResolver;

#[CoversNothing]
class ProductFamilySelectionTest extends SuluTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        self::purgeDatabase();
    }

    public function testBothSelectionTypesHaveAResolver(): void
    {
        /** @var PropertyResolverProviderInterface $provider */
        $provider = self::getContainer()->get('sulu_content.property_resolver_provider');

        $this->assertInstanceOf(
            ProductFamilySelectionPropertyResolver::class,
            $provider->getPropertyResolver('product_family_selection'),
        );
        $this->assertInstanceOf(
            SingleProductFamilySelectionPropertyResolver::class,
            $provider->getPropertyResolver('single_product_family_selection'),
        );
    }

    public function testTheResolvedIdsLoadFromTheDatabase(): void
    {
        /** @var ProductFamilyRepositoryInterface $repository */
        $repository = self::getContainer()->get(ProductFamilyRepositoryInterface::class);
        $family = $repository->create();
        $family->setExternalIdentifier('SPK');
        $family->addTranslation(new ProductFamilyTranslation($family, 'en', 'speakON'));
        $repository->save($family);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->flush();
        $entityManager->clear();

        /** @var string $uuid */
        $uuid = $family->getUuid();

        /** @var ResourceLoaderProvider $loaders */
        $loaders = self::getContainer()->get('sulu_content.resource_loader_provider');
        $resolvable = (new ProductFamilySelectionPropertyResolver())->resolve([$uuid, 'unknown'], 'en')->getContent();
        $this->assertIsArray($resolvable);
        $this->assertInstanceOf(ResolvableResource::class, $resolvable[0]);

        $loader = $loaders->getResourceLoader($resolvable[0]->getResourceLoaderKey());
        $this->assertNotNull($loader);
        $loaded = $loader->load([$uuid, 'unknown'], 'en');

        $this->assertSame([
            $uuid => ['uuid' => $uuid, 'externalIdentifier' => 'SPK', 'name' => 'speakON'],
        ], $loaded);
    }
}
