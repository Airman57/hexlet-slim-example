<?php

namespace App;

class Validator
{
    public function validate(array $user)
    {
        $errors = [];
        if (isset($user['name']) && strlen($user['name']) <= 4) {
            $errors['name'] = 'Nickname must be grater than 4 characters';
        }
        if (empty($user['email'])) {
            $errors['email'] = "Can't be blank";
        }

        return $errors;
    }
}