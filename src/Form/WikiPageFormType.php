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

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->withBuilder($builder, function () use ($options): void {
            $this->addTextField('title', [
                'label' => 'wiki.form.title',
            ]);
            $this->addTypedField('content', TiptapEditorType::class, [
                'label'  => 'wiki.form.content',
                'config' => $options['tiptap_config'],
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => WikiPageFormData::class,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
            'tiptap_config'      => 'notion',
        ]);

        $resolver->setAllowedTypes('tiptap_config', 'string');
    }
}
