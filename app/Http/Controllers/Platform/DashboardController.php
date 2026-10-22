<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Support\Platform\PlatformAudit;
use Illuminate\View\View;

/**
 * The platform console's overview (Phase 5): how many businesses there are,
 * by status. Businesses themselves carry no tenant scope.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $byStatus = Business::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        PlatformAudit::record('dashboard.viewed');

        return view('platform.dashboard', [
            'byStatus' => $byStatus,
            'total' => $byStatus->sum(),
            'operators' => User::query()->where('is_platform_operator', true)->count(),
        ]);
    }
}
