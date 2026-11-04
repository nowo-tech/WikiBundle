<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Nowo\FormKitBundle\Attribute\FormKitConfig;
use Nowo\FormKitBundle\Form\FormOptionsTrait;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\WikiBundle;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The no-JavaScript comment form of the comments panel (1.16): a page-level comment (Markdown, at most
 * {@see WikiCommentService::MAX_BODY_CHARS} characters), posted to `…/comments/new` with the page's comment CSRF
 * token ({@see WikiCommentService::csrfTokenId()}). Nameless: the fields post as `body` and `_token`.
 *
 * @extends AbstractType<array<string, mixed>>
 */
#[FormKitConfig('wiki')]
final class WikiCommentType extends AbstractType
{
    use FormOptionsTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('body', TextareaType::class, [
            'label' => 'wiki.comments.new_label',
            'help'  => 'wiki.comments.new_help',
            'attr'  => ['rows' => 3, 'maxlength' => WikiCommentService::MAX_BODY_CHARS, 'data-testid' => 'wiki-comments-fallback-body'],
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
