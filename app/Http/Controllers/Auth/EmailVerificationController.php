<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, $id, $hash)
    {
        // Cek apakah signature masih valid (termasuk expired)
        if (!$request->hasValidSignature()) {
            return response()->json([
                'message' => 'Verification link has expired. Please request a new verification email.'
            ], 403);
        }

        $user = User::findOrFail($id);

        if (!hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'message' => 'Invalid verification token.'
            ], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email has already been verified.'
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email successfully verified.'
        ]);
    }

    public function resend(Request $request)
    {
        $request->validate([
            'id' => 'required'
        ]);

        $user = User::find($request->id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email Anda sudah terverifikasi.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Link verifikasi baru telah dikirim ke email Anda. Silakan cek inbox/spam.'
        ], 200);
    }
}