<?php

namespace App\Tests\Controller;

use App\Entity\Chapter;
use App\Entity\Formation;
use App\Enum\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FormationControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine.orm.entity_manager');

        // Repart d'une base propre (le contenu vient normalement de la sync).
        foreach ($this->em->getRepository(Formation::class)->findAll() as $formation) {
            $this->em->remove($formation);
        }
        $this->em->flush();
    }

    /**
     * Crée une formation avec un chapitre, contournant la sync.
     */
    private function createFormation(string $slug, Visibility $visibility): Formation
    {
        $formation = (new Formation())
            ->setSlug($slug)
            ->setTitle('Titre '.$slug)
            ->setDescription('<p>Description de la formation.</p>')
            ->setVisibility($visibility);

        $chapter = (new Chapter())
            ->setSlug('introduction')
            ->setTitle('Introduction')
            ->setPosition(1);
        $formation->addChapter($chapter);

        $this->em->persist($formation);
        $this->em->flush();

        return $formation;
    }

    public function testShowDisplaysPresentationAndChapterPlan(): void
    {
        $this->createFormation('symfony', Visibility::PUBLIC);

        $this->client->request('GET', '/formations/symfony');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Titre symfony');
        self::assertSelectorTextContains('h2', 'Plan des chapitres');
        self::assertSelectorTextContains('ol', 'Introduction');
    }

    public function testBetaFormationIsAccessible(): void
    {
        $this->createFormation('vim', Visibility::BETA);

        $this->client->request('GET', '/formations/vim');

        self::assertResponseIsSuccessful();
    }

    public function testDraftFormationReturns404(): void
    {
        $this->createFormation('cachee', Visibility::DRAFT);

        $this->client->request('GET', '/formations/cachee');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownSlugReturns404(): void
    {
        $this->client->request('GET', '/formations/inconnue');

        self::assertResponseStatusCodeSame(404);
    }
}
