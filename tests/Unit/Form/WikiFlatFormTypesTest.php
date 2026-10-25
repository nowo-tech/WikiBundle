<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Form;

use Nowo\WikiBundle\Form\WikiAiAskType;
use Nowo\WikiBundle\Form\WikiImportType;
use Nowo\WikiBundle\Form\WikiSearchType;
use Nowo\WikiBundle\Tests\Support\FormKitTestSupport;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

/**
 * REQ-TWIG-005: flat field names and fixed element ids of the search, AI and import forms.
 */
final class WikiFlatFormTypesTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new HttpFoundationExtension(),
            new PreloadedExtension([
                FormKitTestSupport::withMerger(new WikiSearchType()),
                FormKitTestSupport::withMerger(new WikiAiAskType()),
                FormKitTestSupport::withMerger(new WikiImportType()),
            ], []),
        ];
    }

    public function testSearchWithSpaceChoices(): void
    {
        $form = $this->factory->create(WikiSearchType::class, ['space' => 'eng', 'q' => 'deploy'], [
            'space_choices' => ['eng' => 'Engineering', 'ops' => 'Operations'],
            'element_ids'   => ['space' => 'wiki-search-space', 'q' => 'wiki-search-q'],
        ]);
        $view = $form->createView();

        self::assertSame('GET', $form->getConfig()->getMethod());
        self::assertSame('space', $view['space']->vars['full_name']);
        self::assertSame('wiki-search-space', $view['space']->vars['id']);
        self::assertSame('q', $view['q']->vars['full_name']);
        self::assertSame('deploy', $view['q']->vars['value']);
        self::assertCount(2, $view['space']->vars['choices']);
        self::assertSame('Engineering', $view['space']->vars['choices'][0]->label);
    }

    public function testSearchWithHiddenSpace(): void
    {
        $form = $this->factory->create(WikiSearchType::class, ['space' => 'eng'], ['query_required' => false]);

        self::assertSame('hidden', $form->createView()['space']->vars['block_prefixes'][1]);
        self::assertFalse($form->get('q')->getConfig()->getRequired());
    }

    public function testAiAskNormalisesUnknownSpace(): void
    {
        $form = $this->factory->create(WikiAiAskType::class, null, [
            'space_choices' => ['eng' => 'Engineering'],
            'element_ids'   => ['q' => 'wiki-ai-question'],
            'question_attr' => ['autofocus' => true],
        ]);
        $form->submit(['space' => 'unknown', 'q' => 'How?']);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertNull($form->get('space')->getData());
        self::assertSame('How?', $form->get('q')->getData());
        self::assertTrue($form->createView()['q']->vars['attr']['autofocus']);

        $empty = $this->factory->create(WikiAiAskType::class);
        $empty->submit(null);
        self::assertTrue($empty->isSubmitted());
    }

    public function testImportFallsBackToAutoFormat(): void
    {
        $form = $this->factory->create(WikiImportType::class, null, ['csrf_token_id' => 'wiki_import_1']);
        $view = $form->createView();

        self::assertTrue($view->vars['multipart']);
        self::assertSame('wiki-import-archive', $view['archive']->vars['id']);
        self::assertSame('update_existing', $view['update_existing']->vars['full_name']);

        $form->submit(['format' => 'bogus', 'update_existing' => '1']);

        self::assertTrue($form->isValid());
        self::assertSame('auto', $form->get('format')->getData());
        self::assertTrue($form->get('update_existing')->getData());

        $empty = $this->factory->create(WikiImportType::class, null, ['csrf_token_id' => 'wiki_import_1']);
        $empty->submit(null);
        self::assertNull($empty->get('format')->getData());
    }
}
