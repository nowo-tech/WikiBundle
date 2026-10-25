<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Controller;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

use function array_replace_recursive;

/**
 * CSRF validation for wiki manage POST actions.
 */
trait WikiCsrfTrait
{
    private function isValidCsrf(Request $request, string $tokenId): bool
    {
        $token = $request->request->getString('_token');

        return $this->isCsrfTokenValid($tokenId, $token);
    }

    /**
     * Submits a flat (nameless) Form Type with the POST body and files and validates it, CSRF included
     * (REQ-TWIG-005). Unlike handleRequest(), this also submits when only {@code _token} was posted.
     *
     * @param FormInterface<mixed> $form
     */
    private function isValidSubmittedForm(FormInterface $form, Request $request): bool
    {
        $form->submit(array_replace_recursive($request->request->all(), $request->files->all()));

        return $form->isValid();
    }
}
