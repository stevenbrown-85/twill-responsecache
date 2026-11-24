<?php

namespace StevenBrown85\TwillResponseCache\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use StevenBrown85\TwillResponseCache\Support\CacheInvalidator;
use StevenBrown85\TwillResponseCache\Support\Permissions;

class ClearCacheController extends Controller
{
    public function __invoke(Request $request, CacheInvalidator $invalidator)
    {
        abort_unless(Permissions::currentUserIsAllowed(), 403);

        $user = Auth::guard('twill_users')->user();

        $invalidator->clear($user?->name ?? $user?->email);

        $status = $invalidator->status();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Site cache cleared.',
                'cleared_at' => $status['cleared_at'] ?? null,
                'cleared_by' => $status['cleared_by'] ?? null,
            ]);
        }

        return back()->with('twill-responsecache.cleared', true);
    }
}
