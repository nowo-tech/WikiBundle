<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Form;

use Nowo\TiptapEditorBundle\Form\TiptapEditorType;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\Form\WikiPageFormType;
use Nowo\WikiBundle\Tests\Support\FormKitTestSupport;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class WikiPageFormTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new HttpFoundationExtension(),
            new PreloadedExtension([
                FormKitTestSupport::withMerger(new WikiPageFormType()),
                new TiptapEditorType([
                    'notion' => [
                        'toolbar'    => true,
                        'min_height' => '200px',
                        'form_theme' => 'bootstrap_5_layout',
                        'debug'      => false,
                        'variant'    => 'notion',
                        'theme'      => 'light',
                    ],
                ], 'notion'),
            ], []),
        ];
    }

    public function testSubmitValidData(): void
    {
        $form = $this->factory->create(WikiPageFormType::class, new WikiPageFormData(), [
            'tiptap_config' => 'notion',
        ]);

        $form->submit([
            'title'   => 'Page title',
            'content' => '<p>Body</p>',
        ]);

        self::assertTrue($form->isSynchronized());
        /** @var WikiPageFormData $data */
        $data = $form->getData();
        self::assertSame('Page title', $data->title);
        self::assertSame('<p>Body</p>', $data->content);
    }

    public function testMarkdownEditorIsAPlainTextarea(): void
    {
        $form = $this->factory->create(WikiPageFormType::class, new WikiPageFormData(), [
            'editor_type'    => 'markdown',
            'editor_options' => ['upload_url' => '/upload'],
        ]);

        self::assertInstanceOf(TextareaType::class, $form->get('content')->getConfig()->getType()->getInnerType());
        self::assertSame('wiki.editor.markdown_help', $form->get('content')->getConfig()->getOption('help'));
        self::assertSame('1', $form->get('content')->getConfig()->getOption('attr')['data-nowo-wiki-markdown']);

        $form->submit(['title' => 'Notes', 'content' => null]);
        /** @var WikiPageFormData $data */
        $data = $form->getData();
        self::assertSame('', $data->content);
    }

    public function testMarkdownEditorUsesTheHostFormTypeWithTheEditorOptions(): void
    {
        $form = $this->factory->create(WikiPageFormType::class, new WikiPageFormData(), [
            'editor_type'        => 'markdown',
            'markdown_form_type' => TextType::class,
            'editor_options'     => ['attr' => ['data-upload' => '/upload']],
        ]);

        $content = $form->get('content')->getConfig();
        self::assertInstanceOf(TextType::class, $content->getType()->getInnerType());
        self::assertSame('/upload', $content->getOption('attr')['data-upload']);

        $form->submit(['title' => 'Notes', 'content' => '# Title']);
        /** @var WikiPageFormData $data */
        $data = $form->getData();
        self::assertSame('# Title', $data->content);
    }
}
