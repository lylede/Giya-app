<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserAccountStatusTest extends TestCase
{
    public function test_suspended_status_is_detected(): void
    {
        $user = new User();
        $user->forceFill(['status' => 'Suspended']);

        $this->assertTrue($user->isSuspended());
    }

    public function test_active_user_is_not_suspended(): void
    {
        $user = new User();
        $user->forceFill(['status' => 'Active']);

        $this->assertFalse($user->isSuspended());
    }

    public function test_admin_role_is_detected(): void
    {
        $user = new User();
        $user->forceFill(['role' => 'admin']);

        $this->assertTrue($user->isAdmin());
    }

    public function test_devotee_is_not_treated_as_admin(): void
    {
        $user = new User();
        $user->forceFill(['role' => 'devotee']);

        $this->assertFalse($user->isAdmin());
    }
}
