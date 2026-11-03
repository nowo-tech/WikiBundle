<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Save version (1.15): the label of a named version (1–120 characters).
 *
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig('wiki')]
final class WikiVersionLabelType extends AbstractType
{
    use FormOptionsTrait;

    /**
     * CSRF token id of the form of a page.
     */
    public static function csrfTokenId(string $pageId): string
    {
        return 'wiki_version_' . $pageId;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', TextType::class, [
            'label'       => 'wiki.history.label',
            'help'        => 'wiki.history.label_help',
            'constraints' => [new NotBlank(), new Length(max: 120)],
            'attr'        => ['maxlength' => 120],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'             => 'POST',
            'csrf_protection'    => true,
            'csrf_field_name'    => '_token',
            'allow_extra_fields' => true,
            'translation_domain' => WikiBundle::TRANSLATION_DOMAIN,
        ]);
        $resolver->setRequired('csrf_token_id');
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return '';
    }
}
