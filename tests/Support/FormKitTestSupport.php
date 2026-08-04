<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\FormKitBundle\Form\Constraint\ConstraintDefinitionFactory;
use Nowo\FormKitBundle\Form\FormOptionsMerger;

use function call_user_func;
use function method_exists;

/**
 * Injects a minimal FormOptionsMerger (profile {@code wiki}) into Form Kit form types under test.
 */
final class FormKitTestSupport
{
    public static function merger(): FormOptionsMerger
    {
        $profile = [
            'translation_domain' => 'NowoWikiBundle',
            'defaults'           => [
                'attr'     => [],
                'row_attr' => [],
            ],
            'field_types' => [],
        ];

        return new FormOptionsMerger(
            [
                'wiki'    => $profile,
                'default' => $profile,
            ],
            'wiki',
            new ConstraintDefinitionFactory(),
        );
    }

    /**
     * @template T of object
     *
     * @param T $formType
     *
     * @return T
     */
    public static function withMerger(object $formType): object
    {
        if (!method_exists($formType, 'setFormOptionsMerger')) {
            return $formType;
        }

        call_user_func([$formType, 'setFormOptionsMerger'], self::merger());

        return $formType;
    }
}
