<?php
// Ajuste as credenciais do MySQL (XAMPP/WAMP: utilizador root, palavra-passe vazia)
const DB_HOST = '127.0.0.1';
const DB_PORT = 3308;      // porta do MySQL (a sua: 3308)
const DB_NAME = 'carteira';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Não foi possível ligar ao MySQL: ' . $e->getMessage());
        }
    }
    return $pdo;
}
