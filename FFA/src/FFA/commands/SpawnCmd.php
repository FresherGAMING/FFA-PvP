<?php

namespace FFA\commands;

use FFA\FFA;
use FFA\libs\jojoe77777\FormAPI\CustomForm;
use FFA\libs\jojoe77777\FormAPI\SimpleForm;
use FFA\libs\jojoe77777\FormAPI\ModalForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class SpawnCmd extends Command {

    public function __construct(){
        parent::__construct("spawn", "Go to FFA Spawn", "Usage: /spawn", ["lobby"]);
        $this->setPermission("ffa.spawn");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args){
        if(!$sender instanceof Player){
            $sender->sendMessage("Console can't use this command!");
            return;
        }
        FFA::teleportToSpawn($sender);
    }

    
}