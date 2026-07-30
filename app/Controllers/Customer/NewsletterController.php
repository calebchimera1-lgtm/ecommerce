<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\NewsletterSubscriber;
use PDOException;

final class NewsletterController extends Controller
{
    public function subscribe(Request $request): void
    {
        $data = $this->validate($request->all(), ['email' => 'required|email|max:191']);
        $email = mb_strtolower($data['email']);

        try {
            NewsletterSubscriber::create(['email' => $email, 'is_active' => 1]);
            Session::flash('success', "You're subscribed! Watch your inbox for the next Kymera Collection release.");
        } catch (PDOException) {
            // Unique constraint on email - already subscribed. Not an error
            // from the visitor's point of view.
            Session::flash('success', "You're already on the list.");
        }

        $this->back();
    }
}
