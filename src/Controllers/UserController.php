<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;

class UserController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin();

        $users = User::getAll();

        View::render('users.index', [
            'title' => 'User Management & Roles',
            'users' => $users
        ]);
    }

    public function create(Request $request): void
    {
        Auth::requireAdmin();

        $name = trim((string) $request->post('name', ''));
        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('password', '');
        $role = (string) $request->post('role', 'operator');

        if (empty($name) || empty($email) || empty($password)) {
            Session::setFlash('error', 'All fields are required.');
            Response::redirect('/users');
            return;
        }

        if (User::findByEmail($email)) {
            Session::setFlash('error', 'A user with this email already exists.');
            Response::redirect('/users');
            return;
        }

        $userId = User::create([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => in_array($role, ['admin', 'operator'], true) ? $role : 'operator',
            'status' => 'active'
        ]);

        Logger::audit('user_created', 'user', (string) $userId, ['email' => $email, 'role' => $role]);
        Session::setFlash('success', "User '{$name}' created successfully.");
        Response::redirect('/users');
    }

    public function updateStatus(Request $request, array $params): void
    {
        Auth::requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        $status = (string) $request->post('status', 'active');
        $role = (string) $request->post('role', 'operator');

        if ($id === Auth::id() && $status !== 'active') {
            Session::setFlash('error', 'You cannot deactivate your own administrative account.');
            Response::redirect('/users');
            return;
        }

        User::update($id, [
            'status' => in_array($status, ['active', 'inactive', 'suspended'], true) ? $status : 'active',
            'role' => in_array($role, ['admin', 'operator'], true) ? $role : 'operator'
        ]);

        Logger::audit('user_updated', 'user', (string) $id, ['status' => $status, 'role' => $role]);
        Session::setFlash('success', 'User updated successfully.');
        Response::redirect('/users');
    }

    public function delete(Request $request, array $params): void
    {
        Auth::requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        if ($id === Auth::id()) {
            Session::setFlash('error', 'You cannot delete your own account.');
            Response::redirect('/users');
            return;
        }

        User::delete($id);
        Logger::audit('user_deleted', 'user', (string) $id);
        Session::setFlash('success', 'User deleted successfully.');
        Response::redirect('/users');
    }
}
