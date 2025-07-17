<?php
return [
    'host'  =>  "localhost",
    'port'  =>  "3306",
    'name'  =>  "bolso",
    'user'  =>  getenv('USER_MYSQL'),
    'pass'  =>  getenv('MYSQL_PASSWORD'),
    'type'  =>  "mysql",
    'prep'  =>  "1"
];