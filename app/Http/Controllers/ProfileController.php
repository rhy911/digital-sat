<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ProfileService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    protected ProfileService $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function show(Request $request)
    {
        return view('profile.show', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $this->profileService->updateProfile($user, $request->validated());

        if ($user->email_verified_at === null) {
            return redirect()->route('verify.email.notice')->with('success', 'Profile updated. Please verify your new email.');
        }

        return redirect()->back()->with('success', 'Profile updated successfully.');
    }
}
