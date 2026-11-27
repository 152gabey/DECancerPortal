<?php
// Put this at the very top of any page that needs a signed-in user.
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// True if the signed-in user has one of the given roles, e.g. hasRole('researcher', 'admin')
function hasRole(string ...$roles): bool
{
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

// Stops the page with an error unless the user has one of the given roles.
function requireRole(string ...$roles): void
{
    if (!hasRole(...$roles)) {
        http_response_code(403);
        exit('You do not have access to this page.');
    }
}
