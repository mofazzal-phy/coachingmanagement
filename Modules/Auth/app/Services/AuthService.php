<?php

namespace Modules\Auth\app\Services;

use App\Models\User;

class AuthService
{
    public function register(array $data): User
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => bcrypt($data['password']),
            'phone'    => $data['phone'] ?? null,   // Apnar model-e phone field chilo tai add kora valo
            'avatar'   => $data['avatar'] ?? null,
        ]);

        // Role assign korar age check korun Spatie package setup ache kina
        $user->assignRole('student');

        return $user;
    }
}