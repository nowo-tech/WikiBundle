<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

use function array_keys;

/**
 * The "Move" form of a page (1.14, page tree): the new parent (empty: top level) and the 1-based position among its
 * children (empty: last). The single-pointer and no-JavaScript alternative to dragging in the tree (WCAG 2.5.7).
 *
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig('wiki')]
final class WikiPageMoveType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * CSRF token id of the form of a page.
     */
    public static function csrfTokenId(string $pageId): string
    {
        return 'wiki_move_' . $pageId;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $parents */
        $parents = $options['parent_choices'];

        $builder->add('parent', ChoiceType::class, [
            'required'                  => false,
            'label'                     => 'wiki.tree.move_parent',
            'placeholder'               => 'wiki.tree.move_root',
            'choices'                   => array_keys($parents),
            'choice_value'              => static fn (?string $id): string => (string) $id,
            'choice_label'              => static fn (string $id): string => $parents[$id] ?? $id,
            'choice_translation_domain' => false,
        ]);
        $builder->add('position', IntegerType::class, [
            'required'    => false,
            'label'       => 'wiki.tree.move_position',
            'help'        => 'wiki.tree.move_position_help',
            'constraints' => [new Range(min: 1, max: 100000)],
            'attr'        => ['min' => 1],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'POST',
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'allow_extra_fields' => true,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
            'parent_choices'     => [],
        ]);
        $resolver->setRequired('csrf_token_id');
        $resolver->setAllowedTypes('parent_choices', 'array');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
