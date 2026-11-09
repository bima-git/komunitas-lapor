<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    // Admin boleh semua; warga hanya laporannya sendiri.
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function view(User $user, Report $report): bool
    {
        return $user->id === $report->user_id;
    }
}