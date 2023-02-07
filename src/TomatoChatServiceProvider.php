<?php

namespace TomatoPHP\TomatoChat;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use TomatoPHP\TomatoChat\Console\TomatoChatInstall;
use TomatoPHP\TomatoChat\Menus\ChatMenu;
use TomatoPHP\TomatoPHP\Services\Menu\TomatoMenuRegister;


class TomatoChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        app()->bind('TomatoChatMessenger', function () {
            return new \TomatoPHP\TomatoChat\Services\TomatoChatMessenger;
        });
    }

    public function boot(): void
    {
        //Register generate command
        $this->commands([
            TomatoChatInstall::class,
        ]);

        // Load user's avatar folder from package's config
        $userAvatarFolder = json_decode(json_encode(include(__DIR__.'/../config/tomato-chat.php')))->user_avatar->folder;

        //Register Config file
        $this->mergeConfigFrom(__DIR__.'/../config/tomato-chat.php', 'tomato-chat');

        //Publish Config
        $this->publishes([
            __DIR__.'/../config/tomato-chat.php' => config_path('tomato-chat.php'),
        ], 'tomato-chat-config');

        //Register Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/2022_01_10_99999_add_active_status_to_users.php' => database_path('migrations/' . date('Y_m_d') . '_999999_add_active_status_to_users.php'),
            __DIR__ . '/database/migrations/2022_01_10_99999_add_avatar_to_users.php' => database_path('migrations/' . date('Y_m_d') . '_999999_add_avatar_to_users.php'),
            __DIR__ . '/database/migrations/2022_01_10_99999_add_dark_mode_to_users.php' => database_path('migrations/' . date('Y_m_d') . '_999999_add_dark_mode_to_users.php'),
            __DIR__ . '/database/migrations/2022_01_10_99999_add_messenger_color_to_users.php' => database_path('migrations/' . date('Y_m_d') . '_999999_add_messenger_color_to_users.php'),
            __DIR__ . '/database/migrations/2022_01_10_99999_create_favorites_table.php' => database_path('migrations/' . date('Y_m_d') . '_999999_create_favorites_table.php'),
            __DIR__ . '/database/migrations/2022_01_10_99999_create_messages_table.php' => database_path('migrations/' . date('Y_m_d') . '_999999_create_messages_table.php'),
        ], 'tomato-chat-migrations');

        // Models
        $isV8 = explode('.', app()->version())[0] >= 8;
        $this->publishes([
            __DIR__ . '/Models' => app_path($isV8 ? 'Models' : '')
        ], 'tomato-chat-models');

        // Controllers
        $this->publishes([
            __DIR__ . '/Http/Controllers' => app_path('Http/Controllers/TomatoChat')
        ], 'tomato-chat-controllers');

        // Assets
        $this->publishes([
            // CSS
            __DIR__ . '/../resources/assets/css' => public_path('css/tomato-chat'),
            // JavaScript
            __DIR__ . '/../resources/assets/js' => public_path('js/tomato-chat'),
            // Images
            __DIR__ . '/../resources/assets/imgs' => storage_path('app/public/' . $userAvatarFolder),
        ], 'tomato-chat-assets');

        //Register views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tomato-chat');

        //Publish Views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/tomato-chat'),
        ], 'tomato-chat-views');

        //Register Langs
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'tomato-chat');

        //Publish Lang
        $this->publishes([
            __DIR__.'/../resources/lang' => app_path('lang/vendor/tomato-chat'),
        ], 'tomato-chat-lang');

        //Register Routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');


        TomatoMenuRegister::registerMenu(ChatMenu::class);
    }
}
