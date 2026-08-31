<?php

return [
    'guard' => 'web', 'passwords' => 'users', 'username' => 'login', 'email' => 'email',
    'home' => '/app', 'views' => false, 'lowercase_usernames' => true,
    'middleware' => ['web'], 'features' => [],
    'redirects' => ['password-reset' => '/login'],
];
