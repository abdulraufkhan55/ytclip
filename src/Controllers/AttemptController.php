<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\PublishingAttempt;

class AttemptController
{
    public function index(Request $request): void
    {
        Auth::requireLogin();

        $outcome = $request->get('outcome');
        $platform = $request->get('platform');

        $attempts = PublishingAttempt::getAll([
            'outcome' => $outcome,
            'platform' => $platform
        ]);

        View::render('attempts.index', [
            'title' => 'Publishing Attempt History',
            'attempts' => $attempts,
            'currentOutcome' => $outcome,
            'currentPlatform' => $platform
        ]);
    }
}
