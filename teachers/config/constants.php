<?php
// ============================================================
// /professor/teachers/config/constants.php
// ============================================================

define('TEACHER_ROOT',   dirname(__DIR__));
define('DB_CONFIG',      TEACHER_ROOT . '/config/db.php');

// Comissões da plataforma
define('COMMISSION_REDACAO', 10.0);
define('COMMISSION_AULA',    20.0);

// Libera link da aula 5 minutos antes
define('LINK_RELEASE_BEFORE', 5 * 60);

// Anti-contato
define('CONTACT_PATTERNS', [
    '/\b\d{2}\s*9\d{4}[-\s]?\d{4}\b/',
    '/\b\d{2}\s*\d{4}[-\s]?\d{4}\b/',
    '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/',
    '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
    '/(?:wa\.me|whatsapp\.com|t\.me|telegram\.me)/',
    '/(?:instagram|tiktok|twitter|facebook)\.com/',
    '/(?:pix|transferência|depósito|conta\s+\d)/i',
]);
define('CONTACT_MAX_ATTEMPTS', 3);

// Saldo mínimo para saque
define('MIN_WITHDRAWAL', 50.00);

// URLs base — detecta se é local ou produção
$_isLocal = (
    isset($_SERVER['HTTP_HOST']) &&
    in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'], true)
);

if ($_isLocal) {
    // Local XAMPP: http://localhost/professor/teachers
    define('TEACHER_BASE_URL', '/professor/teachers');
} else {
    // Produção Hostinger
    define('TEACHER_BASE_URL', '/florescer/teachers');
}

define('TEACHER_VIEWS', TEACHER_BASE_URL . '/views');
define('TEACHER_API',   TEACHER_BASE_URL . '/api');

// ── Mercado Pago ──────────────────────────────────────────────
// TESTE: começa com TEST- | PRODUÇÃO: começa com APP_USR-
// Troque pelo token real antes de subir para produção
if (!defined('MP_ACCESS_TOKEN')) {
    define('MP_ACCESS_TOKEN', 'APP_USR-3183107767265009-042509-06edaf260470c961ebae29648ec8154f-3359901254');
    define('MP_PUBLIC_KEY',   'APP_USR-83f0658e-190e-4a0b-ac76-c831b55616d1');
}