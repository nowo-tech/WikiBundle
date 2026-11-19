<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\WikiBundle\WikiBundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * 1.45 (`favourites.enabled`): the flat POST form of "Add to favourites" / "Remove from favourites": its CSRF field
 * (`_token`) and `favourite` (`1` adds, `0` removes), REQ-TWIG-005 (no raw `<form>` in the templates).
 *
 * @extends AbstractType<mixed>
 */
#[FormKitConfig('wiki')]
final class WikiFavouriteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('favourite', HiddenType::class, ['data' => $options['favourite'], 'mapped' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'POST',
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'allow_extra_fields' => true,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
            'favourite'          => '1',
        ]);
        $resolver->setAllowedValues('favourite', ['0', '1']);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
