<?php

namespace Database\Factories;

class UserFactory
{
    public function definition(): array
    {
        return [
            'name' => 'PPIC Officer',
            'email' => 'ppic@duniakimia.co.id',
            'role' => 'PPIC',
        ];
    }
}
