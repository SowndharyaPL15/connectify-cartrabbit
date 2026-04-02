<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo users for testing
        $users = [
            ['name' => 'Alice Johnson',  'email' => 'alice@demo.com',   'about' => 'Hey there! I am Alice.'],
            ['name' => 'Bob Smith',      'email' => 'bob@demo.com',     'about' => 'Available'],
            ['name' => 'Carol Williams','email' => 'carol@demo.com',   'about' => 'Busy 🚀'],
            ['name' => 'Dave Brown',    'email' => 'dave@demo.com',    'about' => 'At work'],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'password' => Hash::make('password'),
                    'about'    => $userData['about'],
                    'status'   => 'offline',
                ]
            );
        }

        $this->command->info('✅ Demo users created! Password for all: password');
        $this->command->table(
            ['Name', 'Email'],
            collect($users)->map(fn($u) => [$u['name'], $u['email']])->toArray()
        );
    }
}
