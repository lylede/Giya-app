<?php

namespace App\Console\Commands;

use App\Models\Church;
use Illuminate\Console\Command;

/**
 * Fills each parish's office phone, email and Facebook page from the
 * Archdiocese of Cebu parish directory (database/data/parish_contacts.php).
 *
 *   php artisan giya:add-church-contacts --dry-run
 *   php artisan giya:add-church-contacts
 *
 * Only empty fields are filled: a contact an admin typed is kept.
 */
class AddChurchContacts extends Command
{
    protected $signature = 'giya:add-church-contacts {--dry-run} {--file= : Another data file (used by the tests)}';

    protected $description = 'Add parish office phone, email and Facebook from the Archdiocese directory';

    private const FIELDS = ['phone' => 'contact_phone', 'email' => 'contact_email', 'facebook' => 'facebook_url'];

    public function handle(): int
    {
        $list = require ($this->option('file') ?: database_path('data/parish_contacts.php'));
        $dry = (bool) $this->option('dry-run');
        $filled = 0;
        $rows = [];

        foreach (Church::orderBy('name')->get() as $church) {
            $entry = $this->find($list, $church);
            if (! $entry) {
                continue;
            }

            $changes = [];
            foreach (self::FIELDS as $key => $column) {
                if (! empty($entry[$key]) && blank($church->$column)) {
                    $changes[$column] = $entry[$key];
                }
            }

            if ($changes) {
                $dry || $church->update($changes + ['updated_at' => now()]);
                $filled++;
                $rows[] = [$church->name, (string) ($church->municipality ?: $church->location), implode(', ', array_keys($changes))];
            }
        }

        $rows && $this->table(['Destination', 'Town', 'Added'], $rows);
        $this->info(($dry ? 'Would add' : 'Added')." contacts to $filled destinations.");

        return self::SUCCESS;
    }

    /** Same name (or a name it is known by) in the same town. */
    private function find(array $list, Church $church): ?array
    {
        $name = mb_strtolower(trim($church->name));
        $where = mb_strtolower(implode(' ', array_filter([$church->municipality, $church->location, $church->address])));

        foreach ($list as $e) {
            $names = array_map(fn ($n) => mb_strtolower(trim($n)), array_merge([$e['name']], $e['aka'] ?? []));
            $town = trim(mb_strtolower(preg_replace('/\b(city of|city)\b/i', '', $e['town'])));
            if (in_array($name, $names, true) && str_contains($where, $town)) {
                return $e;
            }
        }

        return null;
    }
}
