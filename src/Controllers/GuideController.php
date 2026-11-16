<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;

class GuideController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        View::render('setup.guide', [
            'title' => 'Setup, API Integration & Deployment Guide'
        ]);
    }
}
