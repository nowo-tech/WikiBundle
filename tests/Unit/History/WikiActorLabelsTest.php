<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\History;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiActorLabels;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Spec 234, audit PQ-M1: an actor label (token name) never de-pseudonymises an opaque "Editor xxxx" author.
 */
final class WikiActorLabelsTest extends TestCase
{
    public function testNothingWithoutALabelOrAnAuthor(): void
    {
        $labels = new WikiActorLabels(new WikiAuthorLabel('s', true), true);
        $ana    = new TestUserEntity('ana');

        self::assertNull($labels->visible(null, $ana, true));
        self::assertNull($labels->visible('CI token', null, true), 'a deleted author keeps nothing');
        self::assertNull($labels->visibleOnPage(null, $ana, $this->page()));
        self::assertNull($labels->visibleOnPage('CI token', null, $this->page()));
    }

    public function testNeverBesideAnOpaqueAuthorButAlwaysForThemself(): void
    {
        $labels = new WikiActorLabels(new WikiAuthorLabel('s'), true);
        $ana    = new TestUserEntity('ana');

        self::assertNull($labels->visible('Release token', $ana, false), 'beside "Editor xxxx": hidden');
        self::assertNull($labels->visible('Release token', $ana, false, new TestUserEntity('bob')));
        self::assertSame('ana@corp.example', $labels->visible('ana@corp.example', $ana, false, $ana), 'their own label, whatever it says');
        self::assertSame('Release token', $labels->visible('Release token', $ana, false, new WikiSecurityUserDto('ana')), 'the same person by id');
        self::assertNull($labels->visible('Release token', $ana, false, new stdClass()));
    }

    public function testBesideARealNameOnlyWhenOnAndSafe(): void
    {
        $ana = new TestUserEntity('ana.garcia');

        self::assertSame('Release token', (new WikiActorLabels(new WikiAuthorLabel('s', true), true))->visible('Release token', $ana, true));
        self::assertNull((new WikiActorLabels(new WikiAuthorLabel('s', true), false))->visible('Release token', $ana, true), 'switched off');
        $on = new WikiActorLabels(new WikiAuthorLabel('s', true), true);
        // The safe-name rule of real names: no e-mail, no login or local part, printable Latin text.
        self::assertNull($on->visible('ana@corp.example', $ana, true));
        self::assertNull($on->visible('ana.garcia laptop', $ana, true));
        self::assertNull($on->visible('Ana Garcia token', $ana, true), 'contains the login words');
        self::assertNull($on->visible("\u{1F916}", $ana, true));
    }

    public function testOnAPageTheAuthorLabelDecidesWhetherTheNameIsReal(): void
    {
        $page  = $this->page();
        $named = (new TestUserEntity('u-1'))->withDisplayName('Maria Ruiz');

        self::assertSame('Release token', (new WikiActorLabels(new WikiAuthorLabel('s', true), true))->visibleOnPage('Release token', $named, $page));
        self::assertNull((new WikiActorLabels(new WikiAuthorLabel('s', false), false))->visibleOnPage('Release token', $named, $page), 'show_names off: opaque');
        self::assertNull((new WikiActorLabels(new WikiAuthorLabel('s', true), true))->visibleOnPage('Release token', new TestUserEntity('u-2'), $page), 'no display name: opaque');
        self::assertSame('Release token', (new WikiActorLabels(new WikiAuthorLabel('s', false), false))->visibleOnPage('Release token', $named, $page, $named));
    }

    private function page(): WikiPage
    {
        return new WikiPage(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), 'run', 'Run');
    }
}
