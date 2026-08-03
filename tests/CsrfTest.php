<?php

declare(strict_types=1);

require_once __DIR__ . '/../private/classes/Csrf.php';

function assertCsrf(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

Session::start();
$_SESSION = [];

$token = Csrf::token('milestone-5-test');
assertCsrf(strlen($token) === 64, 'CSRF tokens must contain 32 random bytes encoded as hexadecimal.');
assertCsrf(ctype_xdigit($token), 'CSRF tokens must use a transport-safe hexadecimal encoding.');
assertCsrf(Csrf::token('milestone-5-test') === $token, 'Token retrieval must return the current scoped token.');
assertCsrf(Csrf::validate($token, 'milestone-5-test'), 'The current scoped token must validate.');
assertCsrf(!Csrf::validate('incorrect-token', 'milestone-5-test'), 'An incorrect token must be rejected.');
assertCsrf(!Csrf::validate($token, 'another-scope'), 'A token from another scope must be rejected.');

$input = Csrf::input('milestone-5-test');
assertCsrf(str_contains($input, 'name="csrf_token"'), 'The reusable helper must render the standard token field.');
assertCsrf(str_contains($input, htmlspecialchars($token, ENT_QUOTES, 'UTF-8')), 'The rendered token must be HTML escaped.');

$rotated = Csrf::rotate('milestone-5-test');
assertCsrf($rotated !== $token, 'Token rotation must issue a new token.');
assertCsrf(!Csrf::validate($token, 'milestone-5-test'), 'A rotated token must no longer validate.');
assertCsrf(Csrf::validate($rotated, 'milestone-5-test'), 'The replacement token must validate.');

$rejected = false;
try {
    Csrf::requireValid('incorrect-token', 'milestone-5-test');
} catch (CsrfException $exception) {
    $rejected = $exception->getMessage() === 'Your request could not be verified. Please refresh the page and try again.';
}
assertCsrf($rejected, 'CSRF rejection must use the generic customer-safe message.');

echo "CSRF test passed.\n";
