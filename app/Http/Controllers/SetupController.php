<?php

namespace App\Http\Controllers;

use App\Actions\Instance\CreateInstanceAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SetupController extends Controller
{
    public function show(): View
    {
        $this->abortUnlessOpen();

        return view('pages::setup');
    }

    public function store(Request $request, CreateInstanceAdmin $createInstanceAdmin): RedirectResponse
    {
        $expected = $this->abortUnlessOpen();

        $given = $request->input('token');

        if (! is_string($given) || ! hash_equals($expected, $given)) {
            throw ValidationException::withMessages(['token' => __('That is not the setup token.')]);
        }

        $admin = $createInstanceAdmin->handle($request->all());

        abort_if($admin === null, 404);

        Auth::login($admin);

        return redirect()->to($admin->homeUrl());
    }

    /**
     * Setup exists only while a token is configured and nobody runs the instance yet.
     *
     * @return non-empty-string The configured token
     */
    private function abortUnlessOpen(): string
    {
        $token = config('abonemen.setup_token');

        abort_unless(is_string($token) && $token !== '' && CreateInstanceAdmin::isOpen(), 404);

        return $token;
    }
}
