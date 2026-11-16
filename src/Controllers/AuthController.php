<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;

class AuthController
{
    public function showLogin(Request $request): void
    {
        // If no users exist, redirect to initial setup
        if (User::count() === 0) {
            Response::redirect('/setup');
            return;
        }

        if (Auth::check()) {
            Response::redirect('/dashboard');
            return;
        }

        View::render('auth.login', ['title' => 'Sign In'], 'auth');
    }

    public function login(Request $request): void
    {
        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('password', '');

        if (empty($email) || empty($password)) {
            Session::setFlash('error', 'Please provide both email and password.');
            Response::redirect('/login');
            return;
        }

        $result = Auth::attempt($email, $password);

        if ($result['success']) {
            Session::setFlash('success', "Welcome back, {$result['user']['name']}!");
            Response::redirect('/dashboard');
        } else {
            Session::setFlash('error', $result['message']);
            Response::redirect('/login');
        }
    }

    public function logout(): void
    {
        Auth::logout();
        Session::setFlash('info', 'You have been securely signed out.');
        Response::redirect('/login');
    }

    public function showSetup(): void
    {
        if (User::count() > 0) {
            Response::redirect('/login');
            return;
        }

        View::render('setup.install', ['title' => 'Initial Administrator Setup'], 'auth');
    }

    public function processSetup(Request $request): void
    {
        if (User::count() > 0) {
            Response::redirect('/login');
            return;
        }

        $name = trim((string) $request->post('name', ''));
        $email = trim((string) $request->post('email', ''));
        $password = (string) $request->post('password', '');
        $confirmPassword = (string) $request->post('confirm_password', '');

        if (empty($name) || empty($email) || empty($password)) {
            Session::setFlash('error', 'All fields are required.');
            Response::redirect('/setup');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::setFlash('error', 'Please enter a valid email address.');
            Response::redirect('/setup');
            return;
        }

        if (strlen($password) < 8) {
            Session::setFlash('error', 'Password must be at least 8 characters long.');
            Response::redirect('/setup');
            return;
        }

        if ($password !== $confirmPassword) {
            Session::setFlash('error', 'Passwords do not match.');
            Response::redirect('/setup');
            return;
        }

        $userId = User::create([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active'
        ]);

        Logger::audit('admin_setup', 'user', (string) $userId, ['email' => $email], $userId);
        Session::setFlash('success', 'Administrator account created successfully! You can now log in.');
        Response::redirect('/login');
    }
}
