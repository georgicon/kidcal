<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\User;

class CalendarPolicy
{
    public function update(User $user, Calendar $calendar): bool
    {
        return $user->is($calendar->user);
    }
}
