<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly WikiDemoSeeder $demoSeeder,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $spaceRepo   = $manager->getRepository(WikiSpace::class);
        $pageRepo    = $manager->getRepository(WikiPage::class);
        $engineering = $spaceRepo->findOneBy(['slug' => 'engineering']);

        if ($engineering instanceof WikiSpace
            && $pageRepo->findOneBy(['space' => $engineering, 'slug' => 'getting-started']) instanceof WikiPage) {
            return;
        }

        if ($engineering instanceof WikiSpace) {
            $this->purgeWikiData($manager);
        }

        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'demo@wiki.local']);
        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail('demo@wiki.local')
                ->setPassword('')
                ->setRoles(['ROLE_USER']);
            $manager->persist($user);
            $manager->flush();
        }

        $engineering = new WikiSpace('engineering', 'Engineering', WikiSpaceOwnerScope::User, (string) $user->getId());
        $product     = new WikiSpace('product', 'Product', WikiSpaceOwnerScope::User, (string) $user->getId());
        $manager->persist($engineering);
        $manager->persist($product);
        $manager->flush();

        $this->demoSeeder->seedEngineeringSpace($engineering, $user);
        $this->demoSeeder->seedProductSpace($product, $user);
    }

    private function purgeWikiData(ObjectManager $manager): void
    {
        foreach ($manager->getRepository(WikiPageRevision::class)->findAll() as $revision) {
            $manager->remove($revision);
        }
        foreach ($manager->getRepository(WikiPage::class)->findAll() as $page) {
            $manager->remove($page);
        }
        foreach ($manager->getRepository(WikiSpace::class)->findAll() as $space) {
            $manager->remove($space);
        }
        $manager->flush();
    }
}
