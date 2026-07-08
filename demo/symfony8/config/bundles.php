<?php

declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class            => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class              => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class                      => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class             => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class     => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class                    => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class        => ['dev' => true, 'test' => true],
    Nowo\TwigInspectorBundle\NowoTwigInspectorBundle::class          => ['dev' => true],
    Nowo\TiptapEditorBundle\NowoTiptapEditorBundle::class            => ['all' => true],
    Nowo\WikiBundle\WikiBundle::class                                => ['all' => true],
    Symfony\AI\AiBundle\AiBundle::class                              => ['all' => true],
];
