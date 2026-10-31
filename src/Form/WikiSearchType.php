<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_keys;

/**
 * Wiki search filter (REQ-TWIG-005): GET, no CSRF, flat query names {@code space} and {@code q} (bookmarks unchanged).
 *
 * With {@code space_choices} (slug => name) the space is a select with an "all spaces" option; with {@code null}
 * it is a hidden field (search inside the current space).
 *
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig('wiki')]
final class WikiSearchType extends AbstractType
{
    use FormOptionsTrait;
    use WikiFormIdsTrait;

    /**
     * Adds the `space` field (select when `space_choices` is given, hidden otherwise) and the `q` search field.
     *
     * @param FormBuilderInterface<array<string, mixed>|null> $builder form builder
     * @param array<string, mixed> $options resolved options (`space_choices`, `query_required`)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string>|null $spaces */
        $spaces = $options['space_choices'];

        if ($spaces === null) {
            $builder->add('space', HiddenType::class);
        } else {
            $builder->add('space', ChoiceType::class, [
                'required'                  => false,
                'label'                     => 'wiki.search.pick_space',
                'placeholder'               => 'wiki.search.all_spaces',
                'choices'                   => array_keys($spaces),
                'choice_label'              => static fn (string $slug): string => $spaces[$slug] ?? $slug,
                'choice_translation_domain' => false,
            ]);
        }

        $builder->add('q', SearchType::class, [
            'required' => $options['query_required'],
            'label'    => 'wiki.search.query',
            'attr'     => ['placeholder' => 'wiki.search.placeholder'],
        ]);
    }

    /**
     * Assigns the configured element ids to the child views.
     *
     * @param FormView $view root form view
     * @param FormInterface<array<string, mixed>> $form root form
     * @param array<string, mixed> $options resolved form options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var array<string, string> $ids */
        $ids = $options['element_ids'];
        $this->applyElementIds($view, $ids);
    }

    /**
     * Configures GET without CSRF and the `space_choices`, `query_required` and `element_ids` options.
     *
     * @param OptionsResolver $resolver options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'GET',
            'csrf_protection'    => false,
            'allow_extra_fields' => true,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
            'space_choices'      => null,
            'query_required'     => true,
            'element_ids'        => [],
        ]);
        $resolver->setAllowedTypes('space_choices', ['null', 'array']);
        $resolver->setAllowedTypes('query_required', 'bool');
        $resolver->setAllowedTypes('element_ids', 'array');
    }

    /**
     * Returns an empty block prefix so the form renders flat (nameless-root) field names.
     *
     * @return string empty prefix
     */
    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
