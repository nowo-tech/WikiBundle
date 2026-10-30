<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\TiptapEditorBundle\Form\TiptapEditorType;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\WikiBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Wiki page editor form (title + Tiptap HTML body).
 *
 * @extends AbstractType<WikiPageFormData>
 */
#[FormKitConfig('wiki')]
final class WikiPageFormType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * Adds the `title` text field and the `content` Tiptap editor field.
     *
     * @param FormBuilderInterface<WikiPageFormData|null> $builder form builder
     * @param array<string, mixed> $options resolved options (`tiptap_config` editor preset)
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addTextField('title', [
                'label' => 'wiki.form.title',
            ]);
            $this->addTypedField('content', TiptapEditorType::class, [
                'label'  => 'wiki.form.content',
                'help'   => 'wiki.editor.help',
                'config' => $options['tiptap_config'],
            ] + $options['editor_options']);
        });
    }

    /**
     * Binds the form to WikiPageFormData and defines the `tiptap_config` option (default `notion`)
     * and `editor_options` (extra TiptapEditorType options, 1.9).
     *
     * @param OptionsResolver $resolver options resolver
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => WikiPageFormData::class,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
            'tiptap_config'      => 'notion',
            // 1.9: extra TiptapEditorType options (upload / suggestion hooks), see WikiEvents::EDITOR_OPTIONS.
            'editor_options' => [],
        ]);

        $resolver->setAllowedTypes('tiptap_config', 'string');
        $resolver->setAllowedTypes('editor_options', 'array');
    }
}
