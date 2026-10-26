<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    protected ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->profileService->updateProfile($user, $request->validated());

        if ($user->email_verified_at === null) {
            return redirect()->route('verify.email.notice')->with('success', 'Profile updated. Please verify your new email.');
        }

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }

    public function updateSharing(Request $request): RedirectResponse
    {
        $request->user()->update([
            'share_independent_practice' => $request->boolean('share_independent_practice'),
        ]);

        return redirect()->back()->with('success', 'Privacy preference saved.');
    }
}
