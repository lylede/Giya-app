<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** A report "to" a date includes that whole day (found in QA, 17 Nov 2026). */
class ReportEndDateTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $email, $joined, string $role = 'user'): User
    {
        return User::create([
            'name' => strtok($email, '@'), 'email' => $email, 'password_hash' => Hash::make('Secret123!'),
            'role' => $role, 'status' => 'Active', 'created_at' => $joined, 'updated_at' => now(),
        ]);
    }

    public function test_records_from_the_last_day_of_the_range_are_included(): void
    {
        $admin = $this->person('admin@example.com', now()->subYear(), 'admin');
        $this->person('start@example.com', now()->subDays(7)->setTime(0, 5));
        $this->person('end@example.com', now()->subDay()->setTime(23, 30));
        $this->person('after@example.com', now()->setTime(0, 10));
        $this->person('before@example.com', now()->subDays(8)->setTime(23, 50));

        $csv = $this->actingAs($admin)->get(route('admin.reports.download', [
            'report' => 'users', 'from' => now()->subDays(7)->toDateString(), 'to' => now()->subDay()->toDateString(),
        ]))->assertOk()->streamedContent();

        $this->assertStringContainsString('start@example.com', $csv);
        $this->assertStringContainsString('end@example.com', $csv, 'The last day of the range was left out.');
        $this->assertStringNotContainsString('after@example.com', $csv);
        $this->assertStringNotContainsString('before@example.com', $csv);
    }

    public function test_a_chosen_time_is_kept_exactly(): void
    {
        $admin = $this->person('admin@example.com', now()->subYear(), 'admin');
        $this->person('morning@example.com', now()->subDay()->setTime(9, 0));
        $this->person('evening@example.com', now()->subDay()->setTime(20, 0));

        $csv = $this->actingAs($admin)->get(route('admin.reports.download', [
            'report' => 'users', 'from' => now()->subDays(3)->format('Y-m-d\TH:i'), 'to' => now()->subDay()->setTime(12, 0)->format('Y-m-d\TH:i'),
        ]))->assertOk()->streamedContent();

        $this->assertStringContainsString('morning@example.com', $csv);
        $this->assertStringNotContainsString('evening@example.com', $csv, 'A time picked on the Reports page must not be stretched to the end of the day.');
    }
}
