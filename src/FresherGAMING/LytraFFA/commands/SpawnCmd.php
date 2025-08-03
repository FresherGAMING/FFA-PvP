<?php

namespace FresherGAMING\LytraFFA\commands;

use FresherGAMING\LytraFFA\FFA;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginOwned;
use pocketmine\plugin\PluginOwnedTrait;

class SpawnCmd extends Command implements PluginOwned {

    use PluginOwnedTrait;

    public function __construct(){
        parent::__construct("spawn", "Go to FFA Spawn", "Usage: /spawn", ["lobby"]);
        $this->setPermission("lytraffa.spawn");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args){
        if(!$sender instanceof Player){
            $sender->sendMessage("Console can't use this command!");
            return;
        }
        FFA::teleportToSpawn($sender);
    }

    
}