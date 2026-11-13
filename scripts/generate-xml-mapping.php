<?php

declare(strict_types=1);

/*
 * 1.34.0 (REQ-ARCH-001 rule 2): writes the Doctrine XML mapping of every wiki entity from its ORM attributes into
 * src/Resources/config/doctrine-xml/ (`nowo_wiki.database.mapping_type: xml`). Deliberately not
 * Resources/config/doctrine, so DoctrineBundle's auto_mapping keeps the attributes by default.
 * XmlMappingParityTest fails when the committed files differ from this output.
 *
 * Run it after changing an entity: php scripts/generate-xml-mapping.php
 */

use Nowo\WikiBundle\Tests\Support\WikiXmlMappingGenerator;

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$out  = $root . '/src/Resources/config/doctrine-xml';
if (!is_dir($out) && !mkdir($out, 0o777, true) && !is_dir($out)) {
    throw new RuntimeException('Cannot create ' . $out);
}
foreach (glob($out . '/*.orm.xml') ?: [] as $old) {
    unlink($old);
}
foreach (WikiXmlMappingGenerator::generate($root . '/src/Entity') as $file => $xml) {
    file_put_contents($out . '/' . $file, $xml);
    echo $file, "\n";
}
