<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\TiptapEditorBundle\Form\TiptapEditorType;
use Nowo\WikiBundle\DependencyInjection\Configuration;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\WikiBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function is_string;

/**
 * Wiki page editor form (title + Tiptap HTML body, or a Markdown body with `editor_type: markdown` since 1.11).
 *
 * @extends AbstractType<WikiPageFormData>
 */
#[FormKitConfig('wiki')]
final class WikiPageFormType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * Adds the `title` text field and the `content` field (Tiptap editor, or the Markdown body).
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
            if ($options['editor_type'] === Configuration::EDITOR_MARKDOWN) {
                $this->addMarkdownContent($options);

                return;
            }

            $this->addTypedField('content', TiptapEditorType::class, [
                'label'  => 'wiki.form.content',
                'help'   => 'wiki.editor.help',
                'config' => $options['tiptap_config'],
            ] + $options['editor_options']);
        });
    }

    /**
     * The Markdown body (1.11, `editor.type: markdown`): the host form type of `editor.markdown_form_type` with the
     * editor options, or a plain textarea.
     *
     * @param array<string, mixed> $options resolved options
     */
    private function addMarkdownContent(array $options): void
    {
        /** @var class-string<FormTypeInterface<mixed>>|null $type */
        $type = $options['markdown_form_type'];
        if (is_string($type)) {
            /** @var array<string, mixed> $editorOptions */
            $editorOptions = $options['editor_options'];
            $this->addTypedField('content', $type, [
                'label' => 'wiki.form.content',
                'help'  => 'wiki.editor.markdown_help',
            ] + $editorOptions);

            return;
        }

        $this->addTypedField('content', TextareaType::class, [
            'label'      => 'wiki.form.content',
            'help'       => 'wiki.editor.markdown_help',
            'required'   => false,
            'empty_data' => '',
            'attr'       => [
                'rows'                    => 20,
                'spellcheck'              => 'true',
                'data-nowo-wiki-markdown' => '1',
            ],
        ]);
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
            // 1.11: tiptap (HTML) or markdown (textarea, or markdown_form_type).
            'editor_type'        => Configuration::EDITOR_TIPTAP,
            'markdown_form_type' => null,
        ]);

        $resolver->setAllowedTypes('tiptap_config', 'string');
        $resolver->setAllowedTypes('editor_options', 'array');
        $resolver->setAllowedValues('editor_type', [Configuration::EDITOR_TIPTAP, Configuration::EDITOR_MARKDOWN]);
        $resolver->setAllowedTypes('markdown_form_type', ['null', 'string']);
    }
}
