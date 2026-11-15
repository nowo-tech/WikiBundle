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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function is_string;

/**
 * Wiki page editor form (title + Tiptap HTML body, or a Markdown body with `editor_type: markdown` since 1.11).
 * 1.35: with `placement: true` (the new-page form of `web_ui.new_page_dialog`) an unmapped hidden field, `place`,
 * carries where the new page goes as an intent (`child:<id>`, `above:<id>`, `below:<id>`; the tree menu fills it).
 * The server computes the position. 1.38: with `icon_field: true` an optional `icon` field ({@see WikiSpaceIconType}).
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
                // An empty title reaches the slug check (a flash), not the string property as null (a 500).
                'empty_data' => '',
            ]);
            if ($options['icon_field'] === true) {
                // 1.38 (page_icon.form_field): the space icon's field, checked against the same prefixes.
                $this->addTypedField('icon', WikiSpaceIconType::class, [
                    'label' => 'wiki.page.icon',
                    'help'  => 'wiki.page.icon_help',
                ]);
            }
            if ($options['placement'] === true) {
                // 1.35: read by the controller (WikiPagePlacement::fromValue()); not mapped onto the payload.
                $this->addTypedField('place', HiddenType::class, ['mapped' => false, 'required' => false, 'attr' => ['data-wiki-new-page-place' => '1']]);
            }
            if (Configuration::storesMarkdown($options['editor_type'])) {
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
     * The Markdown body (1.11, `editor.type: markdown`; 1.13 `milkdown`): the host form type of `editor.markdown_form_type` with the
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
            // 1.11: tiptap (HTML) or markdown (textarea, or markdown_form_type); 1.13: milkdown (markdown_form_type).
            'editor_type'        => Configuration::EDITOR_TIPTAP,
            'markdown_form_type' => null,
            // 1.35: the hidden `place` field of the new-page form (web_ui.new_page_dialog).
            'placement' => false,
            // 1.38: the optional page icon field (page_icon.form_field).
            'icon_field' => false,
        ]);
        $resolver->setAllowedTypes('icon_field', 'bool');
        $resolver->setAllowedTypes('placement', 'bool');

        $resolver->setAllowedTypes('tiptap_config', 'string');
        $resolver->setAllowedTypes('editor_options', 'array');
        $resolver->setAllowedValues('editor_type', [Configuration::EDITOR_TIPTAP, Configuration::EDITOR_MARKDOWN, Configuration::EDITOR_MILKDOWN]);
        $resolver->setAllowedTypes('markdown_form_type', ['null', 'string']);
    }
}
