<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Form;

use Nowo\IconSelectorBundle\Emoji\EmojiCatalog;
use Nowo\IconSelectorBundle\Form\IconSelectorType;
use Nowo\IconSelectorBundle\Service\IconListProvider;
use Nowo\IconSelectorBundle\Service\SvgSanitizer;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Form\WikiIconPickerType;
use Nowo\WikiBundle\Form\WikiPageFormType;
use Nowo\WikiBundle\Form\WikiSpaceIconType;
use Nowo\WikiBundle\Form\WikiSpaceType;
use Nowo\WikiBundle\Tests\Support\FormKitTestSupport;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

use function class_exists;

/**
 * 1.39 (page_icon.picker / space_icon.picker: icon_selector, *_icon.emoji): the IconSelector picker as the icon field,
 * limited to the allowed prefixes, with emoji when allowed; the same checks and setter as the text field.
 */
final class WikiIconPickerTypeTest extends TypeTestCase
{
    protected function setUp(): void
    {
        if (!class_exists(EmojiCatalog::class)) {
            self::markTestSkipped('nowo-tech/icon-selector-bundle >= 1.6 (dev dependency) is not installed.');
        }
        parent::setUp();
    }

    protected function getExtensions(): array
    {
        $catalog = new EmojiCatalog();

        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([
                new IconSelectorType(new IconListProvider(['tabler', 'heroicons']), ['tabler', 'heroicons'], '/api/icon-selector/icons', null, new SvgSanitizer(), false, $catalog, '/emoji.json'),
                new WikiIconPickerType(['tabler', 'emoji'], null, $catalog),
                new WikiSpaceIconType(['tabler', 'emoji'], null, $catalog),
                new WikiSpaceType('icon_selector'),
                FormKitTestSupport::withMerger(new WikiPageFormType()),
            ], []),
        ];
    }

    public function testThePickerIsLimitedToTheAllowedPrefixesWithTheEmojiTab(): void
    {
        $form = $this->factory->create(WikiIconPickerType::class);
        $view = $form->createView();

        self::assertSame(['tabler'], $form->getConfig()->getOption('icon_prefixes'));
        self::assertTrue($form->getConfig()->getOption('emoji'));
        self::assertSame('search', $form->getConfig()->getOption('mode'));
        self::assertSame('tabler', $view->vars['attr']['data-icon-selector-prefixes-value']);
        self::assertSame('1', $view->vars['attr']['data-icon-selector-emoji-value']);
        foreach ($view->vars['choices'] as $choice) {
            self::assertStringStartsWith('tabler:', $choice->value);
        }

        $noEmoji = $this->factory->create(WikiIconPickerType::class, null, ['allowed_prefixes' => ['tabler']]);
        self::assertFalse($noEmoji->getConfig()->getOption('emoji'));
    }

    public function testTheSpaceFormUsesThePickerAndStoresEmoji(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1');
        $form  = $this->factory->create(WikiSpaceType::class, $space, ['icon_field' => true]);
        self::assertInstanceOf(WikiIconPickerType::class, $form->get('icon')->getConfig()->getType()->getInnerType());

        $form->submit(['name' => 'Eng', 'icon' => 'emoji:🚀']);
        self::assertTrue($form->isValid());
        self::assertSame('emoji:🚀', $space->getIcon());
    }

    public function testInvalidValuesAreRefusedAndLeftOutOfTheModel(): void
    {
        foreach (['emoji:🚀🚀', 'heroicons-outline:home', 'emoji:rocket'] as $bad) {
            $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1');
            $form  = $this->factory->create(WikiSpaceType::class, $space, ['icon_field' => true]);
            $form->submit(['name' => 'Eng', 'icon' => $bad]);
            self::assertFalse($form->isValid(), $bad);
            self::assertNull($space->getIcon(), $bad);
        }
    }

    public function testThePageFormTakesTheConfiguredIconFieldAndTheTitleClass(): void
    {
        $form = $this->factory->create(WikiPageFormType::class, new WikiPageFormData(), [
            'editor_type'        => 'markdown',
            'icon_field'         => true,
            'icon_field_type'    => WikiIconPickerType::class,
            'icon_field_options' => ['allowed_prefixes' => ['tabler', 'emoji']],
        ]);

        self::assertInstanceOf(WikiIconPickerType::class, $form->get('icon')->getConfig()->getType()->getInnerType());
        self::assertSame('wiki.page.icon', $form->get('icon')->getConfig()->getOption('label'));
        self::assertSame('nowo-wiki-title-input', $form->get('title')->getConfig()->getOption('attr')['class'] ?? null);
        self::assertInstanceOf(TextareaType::class, $form->get('content')->getConfig()->getType()->getInnerType());

        $form->submit(['title' => 'T', 'content' => 'x', 'icon' => 'emoji:🎉']);
        /** @var WikiPageFormData $data */
        $data = $form->getData();
        self::assertSame('emoji:🎉', $data->icon);
    }
}
