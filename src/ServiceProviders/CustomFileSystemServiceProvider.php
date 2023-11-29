<?php

namespace CustomFileSystem\ServiceProviders;

use Illuminate\Support\ServiceProvider;

class CustomFileSystemServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->publishes(
            [__DIR__ . "/../../config/custom-file-system-config.php" => config_path("custom-file-system-config.php") ] ,
            'custom-file-system-config'
        );

    }

}
