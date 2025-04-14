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
    'default' => env('FILESTORAGE_DRIVER', 'local'),

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
        'profile_images' => config('app.name').'/'.env('FILESTORAGE_PROFILE_IMAGES_PATH', config('app.name').'/profile/images'),
        'company_logos' => config('app.name').'/'.env('FILESTORAGE_COMPANY_LOGOS_PATH', config('app.name').'/company/logos'),
    ],
];
