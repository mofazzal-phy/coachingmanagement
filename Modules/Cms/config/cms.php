<?php

return [
    'media' => [
        'base_directory' => 'cms',
        'image' => [
            'max_size' => 4 * 1024 * 1024,
            'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        ],
        'pdf' => [
            'max_size' => 10 * 1024 * 1024,
            'mimes' => ['application/pdf'],
        ],
        'video' => [
            'max_size' => 50 * 1024 * 1024,
            'mimes' => ['video/mp4', 'video/webm', 'video/quicktime'],
        ],
    ],

    'public' => [
        'home_limits' => [
            'sliders' => 6,
            'blog' => 6,
            'testimonials' => 8,
            'galleries' => 12,
            'success_stories' => 6,
            'events' => 6,
            'downloads' => 8,
            'notices' => 5,
        ],
        'default_per_page' => 12,
        'max_per_page' => 50,
    ],

    'publishable_tables' => [
        'pages',
        'events',
        'galleries',
        'testimonials',
        'sliders',
        'success_stories',
        'study_materials',
        'download_resources',
    ],
];
