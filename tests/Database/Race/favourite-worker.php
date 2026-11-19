<?php

declare(strict_types=1);

/*
 * 1.45 worker: the other request of a favourite race. Inserts the favourite row of (user, page) in an open transaction,
 * prints `ready`, holds it for <hold> seconds, then commits and prints `committed`.
 *
 * Usage: php favourite-worker.php <table> <page id> <user id> <hold seconds>
 */

use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\ValueObject\Uuid;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

[, $table, $pageId, $userId, $hold] = $argv;

$connection = WikiTestDatabase::connect()->getConnection();
$connection->beginTransaction();
$columns = ['id' => Uuid::generate()->toString(), 'page_id' => $pageId, 'user_id' => $userId];
$columns += $table === 'wiki_page_visits' ? ['visited_at' => '2026-11-19 09:00:00'] : ['created_at' => '2026-11-19 09:00:00'];
$connection->insert($table, $columns);
echo "ready\n";
fflush(\STDOUT);
usleep((int) ((float) $hold * 1_000_000));
$connection->commit();
echo "committed\n";
