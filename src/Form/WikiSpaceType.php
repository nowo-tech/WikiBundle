<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Space settings (1.32): the display name and, with `icon_field`, the UX icon ({@see WikiSpaceIconType}).
 * `icon_field` is off by default, so a host that does not want the icon sees no change.
 *
 * @extends AbstractType<WikiSpace>
 */
final class WikiSpaceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label'       => 'wiki.space.name',
            'constraints' => [new NotBlank(), new Length(max: 255)],
            'attr'        => ['maxlength' => 255],
        ]);
        if ($options['icon_field'] === true) {
            $builder->add('icon', WikiSpaceIconType::class);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => WikiSpace::class,
            'icon_field'         => false,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setAllowedTypes('icon_field', 'bool');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'nowo_wiki_space';
    }
}
