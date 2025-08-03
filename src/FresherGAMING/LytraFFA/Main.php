<?php

namespace FresherGAMING\LytraFFA;

use FresherGAMING\LytraFFA\commands\ArenaCmd;
use FresherGAMING\LytraFFA\commands\SpawnCmd;
use FresherGAMING\LytraFFA\EventListener;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase {

    private static self $instance;

    public function onEnable() : void {
        self::$instance = $this;
        $this->saveResource("config.yml");
        $this->saveResource("ffa.yml");
        $this->saveResource("database.yml");
        FFA::init();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        if($this->getConfig()->get("spawn-command-enabled")){
            $this->getServer()->getCommandMap()->register("LytraFFA", new SpawnCmd());
        }
        $this->getServer()->getCommandMap()->register("LytraFFA", new ArenaCmd());
    }

    public static function getInstance(){
        return self::$instance;
    }

    

    

}