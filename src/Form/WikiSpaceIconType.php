<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Throwable;

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
     */
    public function __construct(private readonly array $allowedPrefixes = WikiSpaceIcon::DEFAULT_PREFIXES, private readonly ?object $iconRenderer = null)
    {
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
        $resolver->setDefault('constraints', function (Options $options): array {
            $prefixes = $options['allowed_prefixes'];
            $renderer = $this->iconRenderer;

            return [
                new Length(max: WikiSpaceIcon::MAX_LENGTH),
                new Callback(static function (mixed $value, ExecutionContextInterface $context) use ($prefixes, $renderer): void {
                    if (is_string($value) && $value !== '' && (!WikiSpaceIcon::isValid($value, $prefixes) || !self::exists($renderer, $value))) {
                        $context->buildViolation('wiki.space.icon_invalid')->setTranslationDomain(WikiBundle::TRANSLATION_DOMAIN)->addViolation();
                    }
                }),
            ];
        });
        $resolver->setDefault('setter', static function (Options $options): callable {
            $prefixes = $options['allowed_prefixes'];

            return static function (mixed &$data, mixed $value, FormInterface $form) use ($prefixes): void {
                // An invalid value is left out of the model: the Callback constraint reports it on the field.
                if (!($value === null || $value === '' || (is_string($value) && WikiSpaceIcon::isValid($value, $prefixes)))) {
                    return;
                }
                $value = $value === '' ? null : $value;
                if ($data instanceof WikiSpace) {
                    $data->setIcon($value, $prefixes);

                    return;
                }
                // A DTO or an array of the host: the property path of the field.
                $path = $form->getPropertyPath();
                if ($path !== null && (is_array($data) || is_object($data))) {
                    PropertyAccess::createPropertyAccessor()->setValue($data, $path, $value);
                }
            };
        });
    }

    /**
     * Tells whether the optional UX Icons renderer knows the icon (true without a renderer).
     */
    private static function exists(?object $renderer, string $icon): bool
    {
        if ($renderer === null || !method_exists($renderer, 'renderIcon')) {
            return true;
        }
        try {
            $renderer->renderIcon($icon);
        } catch (Throwable) {
            return false;
        }

        return true;
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
