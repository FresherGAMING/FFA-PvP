<?php

namespace FFA;

use FFA\commands\ArenaCmd;
use FFA\commands\SpawnCmd;
use FFA\EventListener;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\SingletonTrait;

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
            $this->getServer()->getCommandMap()->register("FFA", new SpawnCmd());
        }
        $this->getServer()->getCommandMap()->register("FFA", new ArenaCmd());
    }

    public static function getInstance(){
        return self::$instance;
    }

    

    

}