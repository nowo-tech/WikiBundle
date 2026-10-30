<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function is_array;
use function is_string;

/**
 * Space ZIP import (REQ-TWIG-005): multipart POST with the flat names {@code format}, {@code archive},
 * {@code update_existing} and {@code _token}; CSRF token id {@code wiki_import_<space id>} (unchanged contract).
 * An unknown format falls back to {@code auto}, as before.
 *
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig('wiki')]
final class WikiImportType extends AbstractType
{
    use FormOptionsTrait;
    use WikiFormIdsTrait;

    /**
     * Adds the `format` select, the `archive` ZIP file field and the `update_existing` checkbox; an unknown posted format falls back to `auto`.
     *
     * @param FormBuilderInterface<array<string, mixed>|null> $builder form builder
     * @param array<string, mixed> $options resolved form options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('format', ChoiceType::class, [
            'label'   => 'wiki.import.format',
            'data'    => WikiInterchangeFormat::Auto->value,
            'choices' => [
                'wiki.import.format_auto'    => WikiInterchangeFormat::Auto->value,
                'wiki.import.format_outline' => WikiInterchangeFormat::Outline->value,
                'wiki.import.format_notion'  => WikiInterchangeFormat::Notion->value,
            ],
        ]);
        $builder->add('archive', FileType::class, [
            'required' => true,
            'label'    => 'wiki.import.file',
            'attr'     => ['accept' => '.zip,application/zip'],
        ]);
        $builder->add('update_existing', CheckboxType::class, [
            'required' => false,
            'label'    => 'wiki.import.update_existing',
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $format = $data['format'] ?? null;
            if (!is_string($format) || WikiInterchangeFormat::tryFrom($format) === null) {
                $data['format'] = WikiInterchangeFormat::Auto->value;
                $event->setData($data);
            }
        });
    }

    /**
     * Assigns the historical `wiki-import-*` element ids to the child views.
     *
     * @param FormView $view root form view
     * @param FormInterface<array<string, mixed>> $form root form
     * @param array<string, mixed> $options resolved form options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $this->applyElementIds($view, [
            'format'          => 'wiki-import-format',
            'archive'         => 'wiki-import-archive',
            'update_existing' => 'wiki-import-update',
        ]);
    }

    /**
     * Configures POST with CSRF (`_token` field) and requires the `csrf_token_id` option.
     *
     * @param OptionsResolver $resolver options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'POST',
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'allow_extra_fields' => true,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setRequired('csrf_token_id');
        $resolver->setAllowedTypes('csrf_token_id', 'string');
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
