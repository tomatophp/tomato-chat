<?php

namespace TomatoPHP\TomatoChat\Console;

use Illuminate\Console\Command;
use TomatoPHP\ConsoleHelpers\Traits\HandleFiles;
use TomatoPHP\ConsoleHelpers\Traits\RunCommand;

class TomatoChatInstall extends Command
{
    use RunCommand;
    use HandleFiles;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'tomato-chat:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'install package and publish assets';

    public function __construct()
    {
        parent::__construct();
        $this->publish = __DIR__ . "/../../publish";
    }


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Publish Vendor Assets');
        $this->callSilent('optimize:clear');
        $this->handelFile('resource/css/app.css', resource_path('/css/app.css'));
        $this->handelFile('resource/js/app.js', resource_path('/js/app.js'));
        $this->handelFile('resource/js/bootstrap.js', resource_path('/js/bootstrap.js'));
        $this->handelFile('resource/css/light.mode.css', resource_path('/css/light.mode.css'));
        $this->handelFile('resource/css/style.css', resource_path('/css/style.css'));
        $this->yarnCommand(['add', 'agora-rtc-sdk-ng', 'pusher-js', 'autosize', 'nprogress', 'jquery']);
        $this->yarnCommand(['install']);
        $this->yarnCommand(['build']);
        $this->artisanCommand(["migrate"]);
        $this->artisanCommand(["optimize:clear"]);
        $this->info('🍅 Tomato Chat installed successfully.');
    }
}
