<?php

declare(strict_types=1);

$dbFile = __DIR__ . '/../storage/person_point.sqlite';
if (!is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0775, true);
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        mad_rate REAL DEFAULT NULL,
        updated_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS point_entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        person_name TEXT NOT NULL,
        points INTEGER NOT NULL CHECK (points BETWEEN 1 AND 9999),
        note TEXT,
        converted_mad REAL DEFAULT NULL,
        converted_at TEXT DEFAULT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS solde_adjustments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        giver_person_name TEXT NOT NULL,
        receiver_person_name TEXT NOT NULL,
        solde_delta INTEGER NOT NULL,
        note TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        CHECK (giver_person_name <> receiver_person_name)
    )'
);

$pdo->exec('INSERT OR IGNORE INTO settings (id, mad_rate) VALUES (1, NULL)');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fetchPersonDirectory(PDO $pdo): array
{
    return $pdo->query(
        'SELECT DISTINCT person_name
         FROM (
            SELECT person_name AS person_name FROM point_entries
            UNION ALL
            SELECT giver_person_name AS person_name FROM solde_adjustments
            UNION ALL
            SELECT receiver_person_name AS person_name FROM solde_adjustments
         )
         WHERE person_name IS NOT NULL AND TRIM(person_name) <> ""
         ORDER BY person_name COLLATE NOCASE ASC'
    )->fetchAll(PDO::FETCH_COLUMN);
}
