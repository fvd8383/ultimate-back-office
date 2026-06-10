# Ultimate Back Office

## Local Environment Configuration

Create `private/config/env.php` by copying `private/config/env.example.php`:

```php
<?php

return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'ultimate_back_office',
        'user' => 'your_database_user',
        'password' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Ultimate Back Office',
        'base_url' => '',
    ],
];
```

Update the database values for your local environment. The real `env.php` file is ignored by git and should not be committed.
