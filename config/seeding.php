<?php

// Valores que solo usan los seeders. Vive en config (y no se lee con env() en
// el seeder) para que los tests puedan fijarlo con config() sin tocar el .env.
return [
    // Sin valor por defecto a propósito: ver DatabaseSeeder.
    'super_admin_password' => env('SEED_SUPER_ADMIN_PASSWORD'),
];
