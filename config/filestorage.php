<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Driver
    |--------------------------------------------------------------------------
    |
    | This value determines which of the following storage driver to use
    | as your default driver for all file storage operations. The available
    | drivers are: "local", "aws", "cloudinary", "dropbox", "google"
    |
    */
    'default' => env('FILESTORAGE_DRIVER', 'aws'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Size
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum file size in kilobytes that can be
    | uploaded through the system.
    |
    */
    'max_file_size' => env('FILESTORAGE_MAX_FILE_SIZE', 10240), // 10MB

    /*
    |--------------------------------------------------------------------------
    | Storage Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure the storage disks for your application.
    |
    */
    'disks' => [
        'local' => [
            'disk' => env('FILESTORAGE_LOCAL_DISK', 'public'),
        ],

        'aws' => [
            'disk' => env('FILESTORAGE_AWS_DISK', 's3'),
        ],

        'cloudinary' => [
            'disk' => env('FILESTORAGE_CLOUDINARY_DISK', 'cloudinary'),
        ],

        'dropbox' => [
            'disk' => env('FILESTORAGE_DROPBOX_DISK', 'dropbox'),
        ],

        'google' => [
            'disk' => env('FILESTORAGE_GOOGLE_DISK', 'gcs'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Paths
    |--------------------------------------------------------------------------
    |
    | Here you may configure the storage paths for your application.
    |
    */
    'paths' => [
        'uploads' => config('app.name') . '/' . env('FILESTORAGE_UPLOADS_PATH', config('app.name') . '/uploads'),
        'profile_images' => config('app.name') . '/' . env('FILESTORAGE_PROFILE_IMAGES_PATH', config('app.name') . '/profile/images'),
        'company_logos' => config('app.name') . '/' . env('FILESTORAGE_COMPANY_LOGOS_PATH', config('app.name') . '/company/logos'),
        'resumes' => config('app.name') . '/' . env('FILESTORAGE_RESUMES_PATH', config('app.name') . '/company/resumes'),
        'blog_images' => config('app.name') . '/' . env('FILESTORAGE_BLOG_IMAGES_PATH', config('app.name') . '/blog/images'),
        'blog_banners' => config('app.name') . '/' . env('FILESTORAGE_BLOG_BANNERS_PATH', config('app.name') . '/blog/banners'),
        'hero_images' => config('app.name') . '/' . env('FILESTORAGE_HERO_IMAGES_PATH', config('app.name') . '/website/hero/images'),
        'about_us_images' => config('app.name') . '/' . env('FILESTORAGE_ABOUT_US_IMAGES_PATH', config('app.name') . '/website/about/images'),
        'ad_banner_images' => config('app.name') . '/' . env('FILESTORAGE_AD_BANNER_IMAGES_PATH', config('app.name') . '/website/ad-banner/images'),
    ],
];
