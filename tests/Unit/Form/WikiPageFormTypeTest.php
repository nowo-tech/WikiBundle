<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Form;

use Nowo\TiptapEditorBundle\Form\TiptapEditorType;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\Form\WikiPageFormType;
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
                new WikiPageFormType(),
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
}
