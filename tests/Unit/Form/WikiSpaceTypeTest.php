<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Form;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Form\WikiSpaceIconType;
use Nowo\WikiBundle\Form\WikiSpaceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

/**
 * 1.32: the space form with the optional icon field.
 */
final class WikiSpaceTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([new WikiSpaceIconType(['tabler', 'lucide'])], []),
        ];
    }

    private function space(): WikiSpace
    {
        return new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1');
    }

    public function testIconFieldIsOffByDefault(): void
    {
        $form = $this->factory->create(WikiSpaceType::class, $this->space());

        self::assertTrue($form->has('name'));
        self::assertFalse($form->has('icon'));
    }

    public function testSubmitIgnoresAnIconWhenTheFieldIsOff(): void
    {
        $space = $this->space();
        $form  = $this->factory->create(WikiSpaceType::class, $space);
        $form->submit(['name' => 'Eng', 'icon' => 'tabler:rocket']);

        self::assertNull($space->getIcon());
        self::assertSame('Eng', $space->getName());
    }

    public function testIconFieldOnStoresAValidIcon(): void
    {
        $space = $this->space();
        $form  = $this->factory->create(WikiSpaceType::class, $space, ['icon_field' => true]);
        $form->submit(['name' => 'Eng', 'icon' => 'lucide:book-open']);

        self::assertTrue($form->isValid());
        self::assertSame('lucide:book-open', $space->getIcon());
    }

    public function testEmptyIconClearsIt(): void
    {
        $space = $this->space();
        $space->setIcon('tabler:book');
        $form = $this->factory->create(WikiSpaceType::class, $space, ['icon_field' => true]);
        $form->submit(['name' => 'Eng', 'icon' => '']);

        self::assertTrue($form->isValid());
        self::assertNull($space->getIcon());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'wrong prefix' => ['mdi:book'];
        yield 'no prefix' => ['book'];
        yield 'trailing newline' => ["tabler:book\n"];
        yield 'uppercase' => ['tabler:Book'];
    }

    /**
     * @dataProvider invalid
     */
    public function testInvalidIconIsRejectedByTheForm(string $icon): void
    {
        $space = $this->space();
        $form  = $this->factory->create(WikiSpaceType::class, $space, ['icon_field' => true]);
        $form->submit(['name' => 'Eng', 'icon' => $icon]);

        self::assertFalse($form->isValid());
        self::assertCount(1, $form->get('icon')->getErrors());
    }

    public function testBlockPrefixesAndViews(): void
    {
        $view = $this->factory->create(WikiSpaceType::class, $this->space(), ['icon_field' => true])->createView();

        self::assertContains('nowo_wiki_space', $view->vars['block_prefixes']);
        self::assertContains('nowo_wiki_space_icon', $view['icon']->vars['block_prefixes']);
        self::assertSame(64, $view['icon']->vars['attr']['maxlength']);
    }

    public function testTheSetterLeavesOtherDataAlone(): void
    {
        $form = $this->factory->createBuilder(FormType::class, new class {
            public ?string $icon = null;
        })->add('icon', WikiSpaceIconType::class)->getForm();
        $form->submit(['icon' => 'tabler:book']);

        self::assertTrue($form->isValid());
    }
}
