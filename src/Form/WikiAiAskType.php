<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_key_exists;
use function array_keys;
use function is_array;
use function is_string;

/**
 * AI assistant question (REQ-TWIG-005): POST, flat names {@code space}, {@code q} and {@code _token},
 * CSRF token id {@code wiki_ai_ask} (unchanged contract).
 *
 * An unknown space slug is not a form error: the controller resolves the posted slug itself (404 for a space the
 * user cannot access), so the select is normalised to "all spaces" before validation.
 *
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig('wiki')]
final class WikiAiAskType extends AbstractType
{
    use FormOptionsTrait;
    use WikiFormIdsTrait;

    public const CSRF_TOKEN_ID = 'wiki_ai_ask';

    /**
     * Adds the `space` select and the `q` question field, and normalises an unknown posted space slug to "all spaces" before validation.
     *
     * @param FormBuilderInterface<array<string, mixed>|null> $builder form builder
     * @param array<string, mixed> $options resolved options (`space_choices` slug => name, `question_attr`)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string> $spaces */
        $spaces = $options['space_choices'];

        $builder->add('space', ChoiceType::class, [
            'required'                  => false,
            'label'                     => 'wiki.search.pick_space',
            'label_attr'                => ['class' => 'form-label'],
            'placeholder'               => 'wiki.search.all_spaces',
            'choices'                   => array_keys($spaces),
            'choice_label'              => static fn (string $slug): string => $spaces[$slug] ?? $slug,
            'choice_translation_domain' => false,
            'attr'                      => ['class' => 'form-select'],
        ]);
        $builder->add('q', SearchType::class, [
            'required'   => true,
            'label'      => 'wiki.ai.placeholder',
            'label_attr' => ['class' => 'form-label'],
            'attr'       => ['class' => 'form-control', 'placeholder' => 'wiki.ai.placeholder'] + $options['question_attr'],
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($spaces): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $space = $data['space'] ?? '';
            if (!is_string($space) || !array_key_exists($space, $spaces)) {
                $data['space'] = '';
                $event->setData($data);
            }
        });
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
     * Configures POST with CSRF (`wiki_ai_ask` token id, `_token` field) and the `space_choices`, `element_ids` and `question_attr` options.
     *
     * @param OptionsResolver $resolver options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'POST',
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'csrf_token_id'      => self::CSRF_TOKEN_ID,
            'allow_extra_fields' => true,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
            'space_choices'      => [],
            'element_ids'        => [],
            'question_attr'      => [],
        ]);
        $resolver->setAllowedTypes('space_choices', 'array');
        $resolver->setAllowedTypes('element_ids', 'array');
        $resolver->setAllowedTypes('question_attr', 'array');
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
