<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_values;
use function in_array;

/**
 * 1.39 (`page_icon.picker` / `space_icon.picker: icon_selector`): the page or space icon as the visual picker of
 * nowo-tech/icon-selector-bundle (>= 1.6): search + grid limited to the allowed prefixes (its `icon_prefixes`), the
 * Emoji tab when emoji are allowed, the same `<prefix>:<name>` / `emoji:<grapheme>` value. The checks and the setter
 * are those of {@see WikiSpaceIconType}, so every writer applies one rule. Registered only when IconSelectorBundle is
 * installed (the parent type is its `IconSelectorType`).
 *
 * @extends AbstractType<string|null>
 */
final class WikiIconPickerType extends AbstractType
{
    /** IconSelectorBundle's form type (named as a string: the bundle is optional). */
    public const PARENT = 'Nowo\IconSelectorBundle\Form\IconSelectorType';

    /**
     * @param list<string> $allowedPrefixes accepted prefixes (with "emoji" when emoji are allowed)
     * @param object|null $iconRenderer optional Symfony UX Icons `IconRendererInterface`
     * @param object|null $emojiCatalog IconSelector's `EmojiCatalogInterface`
     */
    public function __construct(
        private readonly array $allowedPrefixes = WikiSpaceIcon::DEFAULT_PREFIXES,
        private readonly ?object $iconRenderer = null,
        private readonly ?object $emojiCatalog = null,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required'           => false,
            'empty_data'         => null,
            'mode'               => 'search',
            'allowed_prefixes'   => $this->allowedPrefixes,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
            // The picker's own labels (placeholder, search, emoji tab) stay in its domain.
            'choice_translation_domain' => 'NowoIconSelectorBundle',
            'placeholder'               => 'placeholder',
            'search_placeholder'        => 'search_placeholder',
            'label'                     => 'wiki.space.icon',
            'help'                      => 'wiki.space.icon_help',
        ]);
        $resolver->setAllowedTypes('allowed_prefixes', 'string[]');
        $resolver->setDefault('icon_prefixes', static fn (Options $options): array => array_values(array_filter(
            $options['allowed_prefixes'],
            static fn (string $prefix): bool => $prefix !== WikiSpaceIcon::EMOJI_PREFIX_NAME,
        )));
        $resolver->setDefault('emoji', static fn (Options $options): bool => in_array(WikiSpaceIcon::EMOJI_PREFIX_NAME, $options['allowed_prefixes'], true));
        $resolver->setDefault('constraints', fn (Options $options): array => WikiSpaceIconType::iconConstraints($options['allowed_prefixes'], $this->iconRenderer, $this->emojiCatalog));
        $resolver->setDefault('setter', fn (Options $options): callable => WikiSpaceIconType::iconSetter($options['allowed_prefixes'], $this->emojiCatalog));
    }

    public function getParent(): string
    {
        return self::PARENT;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'nowo_wiki_icon_picker';
    }
}
