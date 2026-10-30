<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\WikiBundle\WikiBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A flat POST form carrying only its CSRF field ({@code _token}): the restore and unarchive buttons (1.9,
 * REQ-TWIG-005 — no raw {@code <form>} in the templates).
 *
 * @extends AbstractType<mixed>
 */
#[FormKitConfig('wiki')]
final class WikiPostActionType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'POST',
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'allow_extra_fields' => true,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
