<?php

return [
    'required' => 'The :attribute field is required.',
    'string' => 'The :attribute must be text.',
    'enum' => 'Please choose English or Arabic.',
    'email' => 'The :attribute must be a valid email address.',
    'unique' => 'This :attribute is already in use.',
    'regex' => 'The :attribute format is invalid.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'boolean' => 'The :attribute value is invalid.',
    'digits' => 'The :attribute must contain :digits digits.',
    'min' => ['string' => 'The :attribute must have at least :min characters.'],
    'max' => ['string' => 'The :attribute must not exceed :max characters.'],
    'password' => ['letters' => 'The :attribute must contain a letter.', 'numbers' => 'The :attribute must contain a number.'],
    'attributes' => ['locale' => 'display language', 'name' => 'full name', 'username' => 'username', 'email' => 'email address', 'phone' => 'mobile number', 'password' => 'password', 'code' => 'verification code', 'login' => 'email or username'],
];
