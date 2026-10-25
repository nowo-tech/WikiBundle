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

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('format', ChoiceType::class, [
            'label'      => 'wiki.import.format',
            'label_attr' => ['class' => 'form-label'],
            'data'       => WikiInterchangeFormat::Auto->value,
            'choices'    => [
                'wiki.import.format_auto'    => WikiInterchangeFormat::Auto->value,
                'wiki.import.format_outline' => WikiInterchangeFormat::Outline->value,
                'wiki.import.format_notion'  => WikiInterchangeFormat::Notion->value,
            ],
            'attr' => ['class' => 'form-select'],
        ]);
        $builder->add('archive', FileType::class, [
            'required'   => true,
            'label'      => 'wiki.import.file',
            'label_attr' => ['class' => 'form-label'],
            'attr'       => ['class' => 'form-control', 'accept' => '.zip,application/zip'],
        ]);
        $builder->add('update_existing', CheckboxType::class, [
            'required'   => false,
            'label'      => 'wiki.import.update_existing',
            'label_attr' => ['class' => 'form-check-label'],
            'attr'       => ['class' => 'form-check-input'],
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

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $this->applyElementIds($view, [
            'format'          => 'wiki-import-format',
            'archive'         => 'wiki-import-archive',
            'update_existing' => 'wiki-import-update',
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
        ]);
        $resolver->setRequired('csrf_token_id');
        $resolver->setAllowedTypes('csrf_token_id', 'string');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
