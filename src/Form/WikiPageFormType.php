<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\TiptapEditorBundle\Form\TiptapEditorType;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\WikiBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Wiki page editor form (title + Tiptap HTML body).
 *
 * @extends AbstractType<WikiPageFormData>
 */
final class WikiPageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'wiki.form.title',
            ])
            ->add('content', TiptapEditorType::class, [
                'label'  => 'wiki.form.content',
                'config' => $options['tiptap_config'],
            ]);
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
