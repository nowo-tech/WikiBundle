<?php

declare(strict_types=1);

/*
 * 1.55 worker: the other request of a space-create race. Inserts a space row of (team, <owner>, <slug>) in an open
 * transaction, prints `ready`, holds it for <hold> seconds, then commits (or rolls back) and prints what it did.
 *
 * Usage: php space-worker.php <owner> <slug> <hold seconds> <commit|rollback>
 */

use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\ValueObject\Uuid;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

[, $owner, $slug, $hold, $end] = $argv;

$connection = WikiTestDatabase::connect()->getConnection();
$connection->beginTransaction();
$connection->insert('wiki_spaces', [
    'id'               => Uuid::generate()->toString(),
    'created_at'       => '2026-11-23 09:00:00',
    'updated_at'       => '2026-11-23 09:00:00',
    'lock_version'     => 1,
    'slug'             => $slug,
    'name'             => 'Other request',
    'owner_scope_type' => 'team',
    'owner_scope_id'   => $owner,
]);
echo "ready\n";
fflush(\STDOUT);
usleep((int) ((float) $hold * 1_000_000));
if ($end === 'commit') {
    $connection->commit();
    echo "committed\n";
} else {
    $connection->rollBack();
    echo "rolled back\n";
}
