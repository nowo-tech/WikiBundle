<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Form;

use Symfony\Component\Form\FormView;

/**
 * Gives flat (nameless-root) wiki forms the element ids the templates and labels have always used (REQ-TWIG-005).
 */
trait WikiFormIdsTrait
{
    /**
     * @param array<string, string> $ids Child name => element id
     */
    private function applyElementIds(FormView $view, array $ids): void
    {
        foreach ($ids as $child => $id) {
            if (isset($view->children[$child])) {
                $view->children[$child]->vars['id'] = $id;
            }
        }
    }
}
