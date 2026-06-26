<?php

use Flarum\Database\Migration;

return Migration::addColumns('users', [
    'money' => ['float', 'default' => 0]
]);
