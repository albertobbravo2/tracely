<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Documentos de envío (facturas, despachos de aduana). Disco propio y
        // no el `default` para que su privacidad no dependa de FILESYSTEM_DISK:
        // apuntar el default a un bucket público expondría los documentos.
        // `serve` queda desactivado a propósito — se sirven por el controlador,
        // que sí pasa por el middleware de permisos.
        //
        // Con DOCUMENTS_BUCKET definido van a un bucket privado de Cloudflare
        // R2 (API compatible con S3): en Vercel el disco de la función es de
        // solo lectura y /tmp no persiste ni se comparte entre instancias, así
        // que un fichero local desaparece antes de que alguien lo descargue.
        // Sin él (Sail, tests) siguen en disco local. Variables propias y no
        // las AWS_* para que ningún otro disco acabe escribiendo en este bucket.
        // `throw` en R2 para que una subida fallida no deje un Document sin
        // fichero detrás.
        'documents' => env('DOCUMENTS_BUCKET') ? [
            'driver' => 's3',
            'key' => env('DOCUMENTS_ACCESS_KEY_ID'),
            'secret' => env('DOCUMENTS_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('DOCUMENTS_BUCKET'),
            'endpoint' => env('DOCUMENTS_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => true,
            'report' => false,
        ] : [
            'driver' => 'local',
            'root' => storage_path('app/private/documents'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
