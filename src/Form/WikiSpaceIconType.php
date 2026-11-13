<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\ValueObject\WikiSpaceIcon;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
     */
    public function __construct(private readonly array $allowedPrefixes = WikiSpaceIcon::DEFAULT_PREFIXES)
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
        $resolver->setDefault('constraints', static function (Options $options): array {
            $prefixes = $options['allowed_prefixes'];

            return [
                new Length(max: WikiSpaceIcon::MAX_LENGTH),
                new Callback(static function (mixed $value, ExecutionContextInterface $context) use ($prefixes): void {
                    if (is_string($value) && $value !== '' && !WikiSpaceIcon::isValid($value, $prefixes)) {
                        $context->buildViolation('wiki.space.icon_invalid')->setTranslationDomain(WikiBundle::TRANSLATION_DOMAIN)->addViolation();
                    }
                }),
            ];
        });
        $resolver->setDefault('setter', static function (Options $options): callable {
            $prefixes = $options['allowed_prefixes'];

            return static function (mixed $space, mixed $value) use ($prefixes): void {
                // An invalid value is left out of the model: the Callback constraint reports it on the field.
                if ($space instanceof WikiSpace && ($value === null || $value === '' || (is_string($value) && WikiSpaceIcon::isValid($value, $prefixes)))) {
                    $space->setIcon($value === '' ? null : $value, $prefixes);
                }
            };
        });
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
