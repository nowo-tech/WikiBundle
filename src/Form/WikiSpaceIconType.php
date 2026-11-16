<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyPathInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use function is_array;
use function is_object;
use function is_string;

/**
 * Reusable field for {@see WikiSpace::getIcon()} (1.32): a text input checked against the icon format and the allowed
 * prefixes (`nowo_wiki.space_icon.allowed_prefixes`). Inside a form whose data is a WikiSpace it writes through
 * {@see WikiSpace::setIcon()} with the configured prefixes.
 *
 * @extends AbstractType<string|null>
 */
final class WikiSpaceIconType extends AbstractType
{
    /**
     * @param list<string> $allowedPrefixes `space_icon.allowed_prefixes`
     * @param object|null $iconRenderer optional Symfony UX Icons `IconRendererInterface`: when present the field also
     *                                  rejects an icon that does not exist (a missing icon would make `ux_icon()` throw)
     * @param object|null $emojiCatalog 1.39: IconSelector's `EmojiCatalogInterface` (`*_icon.emoji`)
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
            'required'   => false,
            'empty_data' => null,
            // A trailing newline must reach the validator (the setter rejects it too), not be trimmed away.
            'trim'               => false,
            'allowed_prefixes'   => $this->allowedPrefixes,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
            'label'              => 'wiki.space.icon',
            'help'               => 'wiki.space.icon_help',
            'attr'               => ['maxlength' => WikiSpaceIcon::MAX_LENGTH, 'autocomplete' => 'off', 'spellcheck' => 'false'],
        ]);
        $resolver->setAllowedTypes('allowed_prefixes', 'string[]');
        $resolver->setDefault('constraints', fn (Options $options): array => self::iconConstraints($options['allowed_prefixes'], $this->iconRenderer, $this->emojiCatalog));
        $resolver->setDefault('setter', fn (Options $options): callable => self::iconSetter($options['allowed_prefixes'], $this->emojiCatalog));
    }

    /**
     * The field's checks (1.39: shared with {@see WikiIconPickerType}): length and {@see WikiPageIconService::acceptable()}.
     *
     * @param list<string> $prefixes accepted prefixes (with "emoji" when emoji are allowed)
     *
     * @return list<Constraint>
     */
    public static function iconConstraints(array $prefixes, ?object $renderer, ?object $emojiCatalog): array
    {
        return [
            new Length(max: WikiSpaceIcon::MAX_LENGTH),
            new Callback(static function (mixed $value, ExecutionContextInterface $context) use ($prefixes, $renderer, $emojiCatalog): void {
                if (is_string($value) && $value !== '' && !WikiPageIconService::acceptable($value, $prefixes, $renderer, $emojiCatalog)) {
                    $context->buildViolation('wiki.space.icon_invalid')->setTranslationDomain(WikiBundle::TRANSLATION_DOMAIN)->addViolation();
                }
            }),
        ];
    }

    /**
     * The field's setter (1.39: shared with {@see WikiIconPickerType}): a WikiSpace through {@see WikiSpace::setIcon()},
     * a DTO / array through its property path; an invalid value is left out of the model.
     *
     * @param list<string> $prefixes accepted prefixes
     * @param object|null $emojiCatalog IconSelector's `EmojiCatalogInterface` (no catalogue: no emoji reaches the model)
     */
    public static function iconSetter(array $prefixes, ?object $emojiCatalog = null): callable
    {
        return static function (mixed &$data, mixed $value, FormInterface $form) use ($prefixes, $emojiCatalog): void {
            // An invalid value is left out of the model: the Callback constraint reports it on the field. An emoji is
            // checked against the catalogue before it reaches the model (audit RS-I5); an icon's existence stays the
            // constraint's (as in 1.38).
            if (!($value === null || $value === '' || (is_string($value) && WikiSpaceIcon::isValid($value, $prefixes)
                && (!WikiSpaceIcon::isEmoji($value) || WikiPageIconService::acceptable($value, $prefixes, null, $emojiCatalog))))) {
                return;
            }
            $value = $value === '' ? null : $value;
            if ($data instanceof WikiSpace) {
                $data->setIcon($value, $prefixes);

                return;
            }
            // A DTO or an array of the host: the property path of the field.
            $path = $form->getPropertyPath();
            if ($path instanceof PropertyPathInterface && (is_array($data) || is_object($data))) {
                PropertyAccess::createPropertyAccessor()->setValue($data, $path, $value);
            }
        };
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'nowo_wiki_space_icon';
    }
}
