<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/** A devotee may read and dismiss only their own notifications. */
class NotificationPolicy
{
    public function update(User $user, Notification $notification): bool
    {
        return (int) $notification->user_id === (int) $user->id;
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $this->update($user, $notification);
    }
}
