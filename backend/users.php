<?php
define('IS_API_ROUTE', true);
require_once __DIR__ . '/config.php';

// Strict Super Admin Access
$currentUser = require_role('super_admin');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $users = get_json_file('users') ?? [];
    $safeUsers = array_map(function($u) {
        unset($u['password']);
        unset($u['totp_secret']);
        return $u;
    }, $users);
    json_response('success', 'Users retrieved', $safeUsers);
}

if ($method === 'DELETE' || (isset($_GET['action']) && $_GET['action'] === 'delete')) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_GET['id'] ?? null;

    if (!$id) {
        json_response('error', 'User ID is required');
    }

    if ($id === $currentUser['userId']) {
        json_response('error', 'Cannot delete active logged-in account');
    }

    $users = get_json_file('users') ?? [];
    $filtered = array_values(array_filter($users, function($u) use ($id) {
        return $u['id'] !== $id;
    }));

    if (count($users) === count($filtered)) {
        json_response('error', 'User not found');
    }

    save_json_file('users', $filtered);
    SecurityLogger::logEvent($currentUser['username'], 'super_admin', 'API_USER_DELETED', "Deleted user account ID {$id}");
    json_response('success', 'User account deleted successfully');
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Handle explicit action
    if (($input['action'] ?? '') === 'delete') {
        $id = $input['id'] ?? null;
        if (!$id) json_response('error', 'User ID required');
        if ($id === $currentUser['userId']) json_response('error', 'Cannot delete active account');

        $users = get_json_file('users') ?? [];
        $filtered = array_values(array_filter($users, function($u) use ($id) {
            return $u['id'] !== $id;
        }));
        save_json_file('users', $filtered);
        SecurityLogger::logEvent($currentUser['username'], 'super_admin', 'API_USER_DELETED', "Deleted user account ID {$id}");
        json_response('success', 'User deleted successfully');
    }

    if (!$input || empty($input['username']) || empty($input['name'])) {
        json_response('error', 'Username and name are required');
    }

    $users = get_json_file('users') ?? [];

    // Edit / Toggle case
    if (!empty($input['id'])) {
        foreach ($users as &$u) {
            if ($u['id'] === $input['id']) {
                if (isset($input['status'])) {
                    $u['status'] = $input['status'];
                }
                if (!empty($input['name'])) $u['name'] = trim($input['name']);
                if (!empty($input['role'])) $u['role'] = $input['role'];
                if (!empty($input['password'])) {
                    $valRes = PasswordPolicy::validatePassword($input['password']);
                    if (!$valRes['valid']) {
                        json_response('error', 'Password policy failed: ' . implode(' ', $valRes['errors']));
                    }
                    $u['password'] = PasswordPolicy::hashPassword($input['password']);
                }
                break;
            }
        }
        save_json_file('users', $users);
        SecurityLogger::logEvent($currentUser['username'], 'super_admin', 'API_USER_UPDATED', "Updated user account ID {$input['id']}");
        json_response('success', 'User updated successfully');
    }

    // Add case
    foreach ($users as $u) {
        if (strtolower($u['username']) === strtolower($input['username'])) {
            json_response('error', 'Username already exists');
        }
    }

    $rawPass = !empty($input['password']) ? trim($input['password']) : '';
    if (empty($rawPass)) {
        json_response('error', 'Password is required');
    }

    $valRes = PasswordPolicy::validatePassword($rawPass, $input['username'], $input['name']);
    if (!$valRes['valid']) {
        json_response('error', 'Password policy failed: ' . implode(' ', $valRes['errors']));
    }

    $newUser = [
        'id' => 'user-' . time(),
        'username' => trim($input['username']),
        'password' => PasswordPolicy::hashPassword($rawPass),
        'name' => trim($input['name']),
        'role' => !empty($input['role']) ? $input['role'] : 'salesman',
        'email' => trim($input['email'] ?? ''),
        'phone' => trim($input['phone'] ?? ''),
        'status' => 'active',
        'totp_secret' => '',
        'totp_enabled' => false,
        'force_password_change' => false,
        'createdAt' => date('c')
    ];

    $users[] = $newUser;
    save_json_file('users', $users);
    SecurityLogger::logEvent($currentUser['username'], 'super_admin', 'API_USER_CREATED', "Created user account '{$newUser['username']}'");

    unset($newUser['password']);
    json_response('success', 'User account created successfully', $newUser);
}

json_response('error', 'Invalid HTTP method');
