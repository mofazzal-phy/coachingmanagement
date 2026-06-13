<?php

use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Providers\ConsoleServiceProvider;

return [
    'namespace' => 'Modules',
    'vapor_maintenance_mode' => env('VAPOR_MAINTENANCE_MODE', false),

    'stubs' => [
        'enabled' => false,
        'path' => base_path('vendor/nwidart/laravel-modules/src/Commands/stubs'),
        'files' => [
            'routes/web' => 'routes/web.php',
            'routes/api' => 'routes/api.php',
            'views/index' => 'resources/views/index.blade.php',
            'views/master' => 'resources/views/components/layouts/master.blade.php',
            'scaffold/config' => 'config/config.php',
            'composer' => 'composer.json',
            'assets/js/app' => 'resources/assets/js/app.js',
            'assets/sass/app' => 'resources/assets/sass/app.scss',
            'vite' => 'vite.config.js',
            'package' => 'package.json',
        ],
        'replacements' => [
            'routes/web' => ['LOWER_NAME', 'STUDLY_NAME', 'PLURAL_LOWER_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'routes/api' => ['LOWER_NAME', 'STUDLY_NAME', 'PLURAL_LOWER_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'vite' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME'],
            'json' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'PROVIDER_NAMESPACE'],
            'views/index' => ['LOWER_NAME'],
            'views/master' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME'],
            'scaffold/config' => ['STUDLY_NAME'],
            'composer' => [
                'LOWER_NAME',
                'STUDLY_NAME',
                'VENDOR',
                'AUTHOR_NAME',
                'AUTHOR_EMAIL',
                'MODULE_NAMESPACE',
                'PROVIDER_NAMESPACE',
                'APP_FOLDER_NAME',
            ],
        ],
        'gitkeep' => true,
    ],

    'paths' => [
        'modules' => base_path('Modules'),
        'assets' => public_path('modules'),
        'migration' => base_path('database/migrations'),
        'app_folder' => 'app/',
        'generator' => [
            // API & Core Folders - All set to TRUE
            'actions' => ['path' => 'app/Actions', 'generate' => true],
            'casts' => ['path' => 'app/Casts', 'generate' => false],
            'channels' => ['path' => 'app/Broadcasting', 'generate' => false],
            'class' => ['path' => 'app/Classes', 'generate' => false],
            'command' => ['path' => 'app/Console', 'generate' => false],
            'command_replacements' => ['path' => 'app/Console/Replacements', 'generate' => false],
            'component-class' => ['path' => 'app/View/Components', 'generate' => false],
            'emails' => ['path' => 'app/Emails', 'generate' => true],
            'event' => ['path' => 'app/Events', 'generate' => true],
            'enums' => ['path' => 'app/Enums', 'generate' => true],
            'exceptions' => ['path' => 'app/Exceptions', 'generate' => true],
            'jobs' => ['path' => 'app/Jobs', 'generate' => true],
            'helpers' => ['path' => 'app/Helpers', 'generate' => true],
            'interfaces' => ['path' => 'app/Interfaces', 'generate' => true],
            'listener' => ['path' => 'app/Listeners', 'generate' => true],
            'model' => ['path' => 'app/Models', 'generate' => true],
            'notifications' => ['path' => 'app/Notifications', 'generate' => true],
            'observer' => ['path' => 'app/Observers', 'generate' => true],
            'policies' => ['path' => 'app/Policies', 'generate' => true],
            'provider' => ['path' => 'app/Providers', 'generate' => true],
            'repository' => ['path' => 'app/Repositories', 'generate' => true],
            'resource' => ['path' => 'app/Http/Resources', 'generate' => true],
            'route-provider' => ['path' => 'app/Providers', 'generate' => true],
            'rules' => ['path' => 'app/Rules', 'generate' => true],
            'services' => ['path' => 'app/Services', 'generate' => true],
            'scopes' => ['path' => 'app/Models/Scopes', 'generate' => false],
            'traits' => ['path' => 'app/Traits', 'generate' => true],

            // Http Folders
            'controller' => ['path' => 'app/Http/Controllers', 'generate' => true],
            'filter' => ['path' => 'app/Http/Middleware', 'generate' => true],
            'request' => ['path' => 'app/Http/Requests', 'generate' => true],

            // Config & Database
            'config' => ['path' => 'config', 'generate' => true],
            'factory' => ['path' => 'database/factories', 'generate' => true],
            'migration' => ['path' => 'database/migrations', 'generate' => true],
            'seeder' => ['path' => 'database/seeders', 'generate' => true],

            // Resources & Routes
            'lang' => ['path' => 'lang', 'generate' => false],
            'assets' => ['path' => 'resources/assets', 'generate' => true],
            'component-view' => ['path' => 'resources/views/components', 'generate' => false],
            'views' => ['path' => 'resources/views', 'generate' => true],
            'routes' => ['path' => 'routes', 'generate' => true],

            // Tests
            'test-feature' => ['path' => 'tests/Feature', 'generate' => true],
            'test-unit' => ['path' => 'tests/Unit', 'generate' => true],
        ],
    ],

    'auto-discover' => [
        'migrations' => true,
        'translations' => false,
    ],

    'commands' => ConsoleServiceProvider::defaultCommands()->toArray(),

    'scan' => [
        'enabled' => false,
        'paths' => [base_path('Modules')],
    ],

    'composer' => [
        'vendor' => 'coaching', // Changed vendor name for your project
        'author' => [
            'name' => 'User',
            'email' => 'user@example.com',
        ],
        'composer-output' => false,
    ],

    'register' => [
        'translations' => true,
        'files' => 'register',
    ],

    'activators' => [
        'file' => [
            'class' => FileActivator::class,
            'statuses-file' => base_path('modules_statuses.json'),
        ],
    ],

    'activator' => 'file',
];