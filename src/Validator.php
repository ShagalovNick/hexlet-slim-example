<?php

namespace App;

class Validator
{
    public function validate(array $user)
    {
        if (strlen($user['name']) < 3) {
            return "Error name: minimum 4 chars";
        }
        return '';
    }
}

