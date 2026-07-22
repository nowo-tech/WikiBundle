<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\TiptapEditorBundle\Form\TiptapEditorType;
use Nowo\WikiBundle\Form\WikiPageFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

use const JSON_THROW_ON_ERROR;

final class WikiControllerContainerBuilder
{
    /**
     * @param array<string, mixed> $services
     */
    public static function bind(AbstractController $controller, ?UserInterface $user = null, array $services = []): Container
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tokenStorage = new TokenStorage();
        if ($user instanceof UserInterface) {
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        $csrfManager = new CsrfTokenManager(null, new SessionTokenStorage($requestStack));

        $formTypes = [new WikiPageFormType()];
        if (class_exists(TiptapEditorType::class)) {
            $formTypes[] = new TiptapEditorType([
                'notion' => [
                    'toolbar'    => true,
                    'min_height' => '200px',
                    'form_theme' => 'bootstrap_5_layout',
                    'debug'      => false,
                    'variant'    => 'notion',
                    'theme'      => 'light',
                ],
            ], 'notion');
        }

        $builder = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new CsrfExtension($csrfManager))
            ->addExtension(new ValidatorExtension(Validation::createValidatorBuilder()->getValidator()));

        $builder->addExtension(new PreloadedExtension($formTypes, []));

        $formFactory = $builder->getFormFactory();

        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.csrf.token_manager', $csrfManager);
        $container->set('request_stack', $requestStack);
        $container->set('form.factory', $formFactory);
        $container->setParameter('kernel.debug', true);

        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        if (!$container->has('router')) {
            $router = new class(new RouteCollection(), new RequestContext()) extends UrlGenerator {
                public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
                {
                    return '/generated/' . $name;
                }
            };
            $container->set('router', $router);
        }

        if (!$container->has('twig')) {
            $twig = new class {
                public function render(string $name, array $context = []): string
                {
                    return $name . ' ' . json_encode(array_keys($context), JSON_THROW_ON_ERROR);
                }
            };
            $container->set('twig', $twig);
        }

        if (!$container->has('translator')) {
            $translator = new class implements TranslatorInterface {
                public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
                {
                    return $id;
                }

                public function getLocale(): string
                {
                    return 'en';
                }
            };
            $container->set('translator', $translator);
        }

        $controller->setContainer($container);

        return $container;
    }

    public static function csrfToken(Container $container, string $tokenId): string
    {
        /** @var CsrfTokenManager $manager */
        $manager = $container->get('security.csrf.token_manager');

        return $manager->getToken($tokenId)->getValue();
    }
}
