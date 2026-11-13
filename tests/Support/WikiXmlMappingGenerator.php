<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use BackedEnum;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use UnitEnum;

use function basename;
use function glob;
use function htmlspecialchars;
use function implode;
use function is_bool;
use function ksort;
use function ltrim;
use function sort;

use const ENT_QUOTES;
use const ENT_XML1;

/**
 * 1.34.0 (REQ-ARCH-001 rule 2): the Doctrine XML mapping of every wiki entity, built from its ORM attributes. Used by
 * `scripts/generate-xml-mapping.php` (writes `src/Resources/config/doctrine-xml`) and by `XmlMappingParityTest`
 * (fails when the committed files are not the generator's output). Names the attributes leave implicit stay implicit,
 * so the XML gives the same metadata as the attributes under any naming strategy.
 */
final class WikiXmlMappingGenerator
{
    public const ENTITY_NAMESPACE = 'Nowo\\WikiBundle\\Entity';

    /**
     * @return array<string, string> file name (`<ShortName>.orm.xml`) => XML, sorted by file name
     */
    public static function generate(string $entityDir): array
    {
        $files = [];
        $paths = glob($entityDir . '/*.php');
        $paths = $paths === false ? [] : $paths;
        sort($paths);
        foreach ($paths as $path) {
            /** @var class-string $class */
            $class = self::ENTITY_NAMESPACE . '\\' . basename($path, '.php');
            $xml   = self::entity(new ReflectionClass($class));
            if ($xml !== null) {
                $files[basename($path, '.php') . '.orm.xml'] = $xml;
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * @param ReflectionClass<object> $r
     */
    private static function entity(ReflectionClass $r): ?string
    {
        $entity = self::attr($r, ORM\Entity::class);
        if (!$entity instanceof ORM\Entity) {
            return null;
        }
        $class = $r->getName();
        $table = self::attr($r, ORM\Table::class);
        $a     = ' name="' . self::x($class) . '"';
        if ($entity->repositoryClass !== null) {
            $a .= ' repository-class="' . self::x($entity->repositoryClass) . '"';
        }
        if ($table instanceof ORM\Table && $table->name !== null) {
            $a .= ' table="' . self::x($table->name) . '"';
        }
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!-- Generated from the ORM attributes by scripts/generate-xml-mapping.php (1.34.0, REQ-ARCH-001 rule 2). Do not edit. -->\n"
            . "<doctrine-mapping xmlns=\"http://doctrine-project.org/schemas/orm/doctrine-mapping\"\n"
            . "                  xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\n"
            . "                  xsi:schemaLocation=\"http://doctrine-project.org/schemas/orm/doctrine-mapping https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd\">\n"
            . '    <entity' . $a . ">\n";

        $indexes = self::attrs($r, ORM\Index::class);
        if ($indexes !== []) {
            $xml .= "        <indexes>\n";
            foreach ($indexes as $i) {
                $xml .= '            <index'
                    . ($i->name !== null ? ' name="' . self::x($i->name) . '"' : '')
                    . ($i->columns !== null ? ' columns="' . self::x(implode(',', $i->columns)) . '"' : '')
                    . ($i->fields !== null ? ' fields="' . self::x(implode(',', $i->fields)) . '"' : '')
                    . ($i->flags !== null ? ' flags="' . self::x(implode(',', $i->flags)) . '"' : '')
                    . self::closeWithOptions($i->options ?? [], 'index', '            ');
            }
            $xml .= "        </indexes>\n";
        }
        $uniques = self::attrs($r, ORM\UniqueConstraint::class);
        if ($uniques !== []) {
            $xml .= "        <unique-constraints>\n";
            foreach ($uniques as $u) {
                $xml .= '            <unique-constraint'
                    . ($u->name !== null ? ' name="' . self::x($u->name) . '"' : '')
                    . ($u->columns !== null ? ' columns="' . self::x(implode(',', $u->columns)) . '"' : '')
                    . ($u->fields !== null ? ' fields="' . self::x(implode(',', $u->fields)) . '"' : '')
                    . self::closeWithOptions($u->options ?? [], 'unique-constraint', '            ');
            }
            $xml .= "        </unique-constraints>\n";
        }

        $callbacks = '';
        foreach ($r->getMethods() as $m) {
            foreach ([ORM\PrePersist::class => 'prePersist', ORM\PreUpdate::class => 'preUpdate', ORM\PreRemove::class => 'preRemove', ORM\PostPersist::class => 'postPersist', ORM\PostUpdate::class => 'postUpdate', ORM\PostRemove::class => 'postRemove', ORM\PostLoad::class => 'postLoad'] as $type => $event) {
                if (self::attr($m, $type) !== null) {
                    $callbacks .= '            <lifecycle-callback type="' . $event . '" method="' . self::x($m->getName()) . "\"/>\n";
                }
            }
        }
        if ($callbacks !== '') {
            $xml .= "        <lifecycle-callbacks>\n" . $callbacks . "        </lifecycle-callbacks>\n";
        }

        $parts = ['id' => '', 'field' => '', 'one-to-one' => '', 'one-to-many' => '', 'many-to-one' => ''];
        foreach ($r->getProperties() as $p) {
            if ($p->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            self::property($p, $parts);
        }

        return $xml . implode('', $parts) . "    </entity>\n</doctrine-mapping>\n";
    }

    /**
     * @param array<string, string> $parts
     */
    private static function property(ReflectionProperty $p, array &$parts): void
    {
        $field = $p->getName();
        $id    = self::attr($p, ORM\Id::class) !== null;
        if (($c = self::attr($p, ORM\Column::class)) instanceof ORM\Column) {
            $tag = $id ? 'id' : 'field';
            $s   = '        <' . $tag . ' name="' . self::x($field) . '"';
            if ($c->type !== null) {
                $s .= ' type="' . self::x($c->type) . '"';
            }
            if ($c->name !== null) {
                $s .= ' column="' . self::x($c->name) . '"';
            }
            if ($c->length !== null) {
                $s .= ' length="' . $c->length . '"';
            }
            if ($c->precision !== null) {
                $s .= ' precision="' . $c->precision . '"';
            }
            if ($c->scale !== null) {
                $s .= ' scale="' . $c->scale . '"';
            }
            if (!$id && $c->unique) {
                $s .= ' unique="true"';
            }
            if (!$id && $c->nullable) {
                $s .= ' nullable="true"';
            }
            if ($c->enumType !== null) {
                $s .= ' enum-type="' . self::x($c->enumType) . '"';
            }
            if ($c->columnDefinition !== null) {
                $s .= ' column-definition="' . self::x($c->columnDefinition) . '"';
            }
            $opts = self::options($c->options, '            ');
            $gen  = self::attr($p, ORM\GeneratedValue::class);
            if ($opts === '' && !$gen instanceof ORM\GeneratedValue) {
                $parts[$tag] .= $s . "/>\n";
            } else {
                $parts[$tag] .= $s . ">\n"
                    . ($gen instanceof ORM\GeneratedValue ? '            <generator strategy="' . self::x($gen->strategy) . "\"/>\n" : '')
                    . $opts . '        </' . $tag . ">\n";
            }

            return;
        }
        foreach ([ORM\ManyToOne::class => 'many-to-one', ORM\OneToOne::class => 'one-to-one'] as $type => $tag) {
            $m = self::attr($p, $type);
            if (!$m instanceof ORM\ManyToOne && !$m instanceof ORM\OneToOne) {
                continue;
            }
            $s = '        <' . $tag . ' field="' . self::x($field) . '" target-entity="' . self::x(ltrim((string) $m->targetEntity, '\\')) . '"';
            if ($m->inversedBy !== null) {
                $s .= ' inversed-by="' . self::x($m->inversedBy) . '"';
            }
            if ($m instanceof ORM\OneToOne && $m->mappedBy !== null) {
                $s .= ' mapped-by="' . self::x($m->mappedBy) . '"';
            }
            if ($m->fetch !== 'LAZY') {
                $s .= ' fetch="' . self::x($m->fetch) . '"';
            }
            if ($m instanceof ORM\OneToOne && $m->orphanRemoval) {
                $s .= ' orphan-removal="true"';
            }
            if ($id) {
                $s .= ' id="true"';
            }
            $inner = self::cascade($m->cascade ?? [], '            ');
            foreach (self::attrs($p, ORM\JoinColumn::class) as $j) {
                $inner .= self::joinColumn($j, '            ');
            }
            $parts[$tag] .= $inner === '' ? $s . "/>\n" : $s . ">\n" . $inner . '        </' . $tag . ">\n";
        }
        if (($m = self::attr($p, ORM\OneToMany::class)) instanceof ORM\OneToMany) {
            $s = '        <one-to-many field="' . self::x($field) . '" target-entity="' . self::x(ltrim((string) $m->targetEntity, '\\')) . '" mapped-by="' . self::x((string) $m->mappedBy) . '"';
            if ($m->orphanRemoval) {
                $s .= ' orphan-removal="true"';
            }
            if ($m->fetch !== 'LAZY') {
                $s .= ' fetch="' . self::x($m->fetch) . '"';
            }
            if ($m->indexBy !== null) {
                $s .= ' index-by="' . self::x($m->indexBy) . '"';
            }
            $inner = self::cascade($m->cascade ?? [], '            ');
            if (($o = self::attr($p, ORM\OrderBy::class)) instanceof ORM\OrderBy) {
                $inner .= "            <order-by>\n";
                foreach ($o->value as $f => $dir) {
                    $inner .= '                <order-by-field name="' . self::x((string) $f) . '" direction="' . self::x(match (true) {
                        $dir instanceof BackedEnum => (string) $dir->value,
                        $dir instanceof UnitEnum   => $dir->name === 'Descending' ? 'DESC' : 'ASC',
                        default                    => (string) $dir,
                    }) . "\"/>\n";
                }
                $inner .= "            </order-by>\n";
            }
            $parts['one-to-many'] .= $inner === '' ? $s . "/>\n" : $s . ">\n" . $inner . "        </one-to-many>\n";
        }
        if (self::attr($p, ORM\ManyToMany::class) !== null || self::attr($p, ORM\Embedded::class) !== null) {
            throw new LogicException('Not generated (add it to WikiXmlMappingGenerator): ' . $p->getDeclaringClass()->getName() . '::$' . $field);
        }
    }

    /**
     * @template T of object
     *
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionProperty $r
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private static function attrs(ReflectionClass|ReflectionProperty|ReflectionMethod $r, string $class): array
    {
        $out = [];
        foreach ($r->getAttributes($class, ReflectionAttribute::IS_INSTANCEOF) as $a) {
            $out[] = $a->newInstance();
        }

        return $out;
    }

    /**
     * @template T of object
     *
     * @param ReflectionClass<object>|ReflectionMethod|ReflectionProperty $r
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private static function attr(ReflectionClass|ReflectionProperty|ReflectionMethod $r, string $class): ?object
    {
        return self::attrs($r, $class)[0] ?? null;
    }

    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function closeWithOptions(array $options, string $tag, string $indent): string
    {
        $opts = self::options($options, $indent . '    ');

        return $opts === '' ? "/>\n" : ">\n" . $opts . $indent . '</' . $tag . ">\n";
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function options(array $options, string $indent): string
    {
        if ($options === []) {
            return '';
        }
        $xml = $indent . "<options>\n";
        foreach ($options as $name => $value) {
            // XmlDriver keeps an option as text: a boolean default is written as the 1 / 0 the platforms store.
            $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value; // @phpstan-ignore cast.string
            $xml .= $indent . '    <option name="' . self::x((string) $name) . '">' . self::x($value) . "</option>\n";
        }

        return $xml . $indent . "</options>\n";
    }

    private static function joinColumn(ORM\JoinColumn $j, string $indent): string
    {
        $a = '';
        if ($j->name !== null) {
            $a .= ' name="' . self::x($j->name) . '"';
        }
        if ($j->referencedColumnName !== null && $j->referencedColumnName !== 'id') {
            $a .= ' referenced-column-name="' . self::x($j->referencedColumnName) . '"';
        }
        if ($j->unique) {
            $a .= ' unique="true"';
        }
        if ($j->nullable === false) {
            $a .= ' nullable="false"';
        }
        if ($j->onDelete !== null) {
            $a .= ' on-delete="' . self::x($j->onDelete) . '"';
        }

        return $indent . '<join-column' . $a . "/>\n";
    }

    /**
     * @param list<string> $cascade
     */
    private static function cascade(array $cascade, string $indent): string
    {
        if ($cascade === []) {
            return '';
        }
        $xml = $indent . "<cascade>\n";
        foreach ($cascade as $c) {
            $xml .= $indent . '    <cascade-' . $c . "/>\n";
        }

        return $xml . $indent . "</cascade>\n";
    }
}
