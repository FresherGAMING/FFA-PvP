<?php

namespace FFA\commands;

use FFA\FFA;
use FFA\libs\jojoe77777\FormAPI\CustomForm;
use FFA\libs\jojoe77777\FormAPI\SimpleForm;
use FFA\libs\jojoe77777\FormAPI\ModalForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class ArenaCmd extends Command {

    public function __construct(){
        parent::__construct("ffa", "Manage FFA arena settings", "Usage: /ffa");
        $this->setPermission("ffa.manage");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args){
        if(!$sender instanceof Player){
            $sender->sendMessage("Console can't use this command!");
            return;
        }
        $menu = new SimpleForm(function($player, $data){
            if($data === null)return;
            match($data){
                "create" => $this->createArena($player),
                "manage" => $this->selectArena($player),
                "setlobby" => $this->setLobby($player)
            };
        });
        $menu->setTitle("§4Manage FFA");
        $menu->addButton("Create new arena", -1, '', "create");
        $menu->addButton("Manage existing arena", -1, '', "manage");
        $menu->addButton("Set FFA Lobby", -1, '', "setlobby");
        $sender->sendForm($menu);
    }

    public function createArena(Player $sender){
        $menu = new CustomForm(function($player, $data){
            if($data === null)return;
            if($data["arenaid"] === "" || $data["arenaid"] === null){
                $player->sendMessage("§cPlease input the arena id");
                return;
            }
            if($data["arenadisplayname"] === "" || $data["arenadisplayname"] === null){
                $player->sendMessage("§cPlease input the arena display name");
                return;
            }
            FFA::addArena($data["arenaid"], $data["arenadisplayname"], [], []);
            $player->sendMessage("§aArena '{$data["arenaid"]}' has been created");
            return;
        });
        $menu->setTitle("§4Create Arena");
        $menu->addInput("Arena ID", '', null, "arenaid");
        $menu->addInput("Arena Display Name", '', null, "arenadisplayname");
        $sender->sendForm($menu);
    }

    public function selectArena(Player $sender){
        $menu = new SimpleForm(function($player, $data){
            if($data === null)return;
            $this->manageArena($player, $data);
        });
        $menu->setTitle("§4Manage FFA");
        foreach(FFA::getArenas() as $id => $arena){
            $menu->addButton($arena->getName(), -1, '', $id);
        }
        $sender->sendForm($menu);
    }

    public function manageArena(Player $sender, string $id){
        $menu = new SimpleForm(function($player, $data)use($sender, $id){
            if($data === null)return;
            match($data){
                0 => $this->addSpawnpoint($sender, $id),
                1 => $this->removeSpawnpoint($sender, $id),
                2 => $this->setKit($sender, $id),
                3 => $this->arenaRemoveConfirmation($sender, $id),
                4 => $this->setArenaDisplayName($sender, $id)
            };
        });
        $menu->setTitle("§4Manage FFA");
        $menu->addButton("Add spawnpoints from your current position");
        $menu->addButton("Remove spawnpoints");
        $menu->addButton("Set kit based on your inventory");
        $menu->addButton("Remove this arena");
        $menu->addButton("Set arena display name");
        $sender->sendForm($menu);
    }

    public function addSpawnpoint(Player $sender, string $id){
        $arena = FFA::getArena($id);
        if(!$arena){
            $sender->sendMessage("§cArena '$id' no longer exist");
            return;
        }
        $pos = $sender->getLocation();
        $arena->addSpawnPoint($pos);
        $x = $pos->getX(); $y = $pos->getY(); $z = $pos->getZ();
        $sender->sendMessage("§aSpawnpoint ($x, $y, $z) has been added to Arena '$id'");
        return;
    }

    public function removeSpawnpoint(Player $sender, string $id){
        $arena = FFA::getArena($id);
        if(empty($arena->getSpawnpoints())){
            return $sender->sendMessage("§cArena '$id' doesn't have any spawnpoints");
        }
        $menu = new SimpleForm(function($player, $data)use($id){
            if($data === null)return;
            $arena = FFA::getArena($id);
            if(!$arena){
                $player->sendMessage("§cArena '$id' no longer exist");
                return;
            }
            $spawnpoints = $arena->getSpawnpoints();
            if(!array_key_exists($data, $spawnpoints)){
                $player->sendMessage("§cSpawnpoint no longer exist");
                return;
            }
            $spawnpointdata = $spawnpoints[$data];
            $arena->removeSpawnpoint($data);
            $player->sendMessage("§aSpawnpoint ({$spawnpointdata[0]}, {$spawnpointdata[1]}, {$spawnpointdata[2]}) of Arena '$id' has been removed");
            return;
        });
        $menu->setTitle("§4Manage FFA");
        foreach($arena->getSpawnpoints() as $id => $pos){
            $x = $pos[0]; $y = $pos[1]; $z = $pos[2];
            $menu->addButton("($x, $y, $z)", -1, '', $id);
        }
        $sender->sendForm($menu);
        return;
    }

    public function setKit(Player $sender, string $id){
        $menu = new ModalForm(function(Player $player, $data)use($id){
            if($data === null)return;
            if($data){
                $arena = FFA::getArena($id);
                if(!$arena){
                    $player->sendMessage("§cArena '$id' no longer exist");
                    return;
                }
                $arena->setKit($player);
                $player->sendMessage("§aKit of Arena '$id' has been updated as your current inventory");
                return;
            }
        });
        $menu->setTitle("§4Manage FFA");
        $menu->setContent("Are you sure want to set the kit as your current inventory ?");
        $menu->setButton1("§aContinue");
        $menu->setButton2("§cBack");
        $sender->sendForm($menu);
        return;
    }

    public function arenaRemoveConfirmation(Player $sender, string $id){
        $menu = new ModalForm(function(Player $player, $data)use($id){
            if($data === null)return;
            if($data){
                $arena = FFA::getArena($id);
                if(!$arena){
                    $player->sendMessage("§cArena '$id' no longer exist");
                    return;
                }
                $player->sendMessage("§aArena '$id' has been deleted");
                FFA::removeArena($id);
            }
        });
        $menu->setTitle("§4Manage FFA");
        $menu->setContent("Are you sure want to delete this arena");
        $menu->setButton1("§aContinue");
        $menu->setButton2("§cBack");
        $sender->sendForm($menu);
        return;
    }

    public function setArenaDisplayName(Player $sender, string $id){
        $menu = new CustomForm(function($player, $data)use($id){
            if($data === null)return;
            $arena = FFA::getArena($id);
            if(!$arena){
                $player->sendMessage("§cArena '$id' no longer exist");
                return;
            }
            if($data["arenadisplayname"] === "" || $data["arenadisplayname"] === null){
                $player->sendMessage("§cPlease input the new arena display name");
                return;
            }
            $arena->setDisplayName($data["arenadisplayname"]);
            $player->sendMessage("§aArena $id's display name has been changed to {$data["arenadisplayname"]}");
            return;
        });
        $menu->setTitle("§4Manage Arena '$id'");
        $menu->addInput("New Arena Display Name", '', null, "arenadisplayname");
        $sender->sendForm($menu);
    }

    public function setLobby(Player $sender){
        $menu = new ModalForm(function(Player $player, $data){
            if($data === null)return;
            if($data){
                FFA::setSpawn($player->getLocation());
                $loc = $player->getLocation();
                $player->sendMessage("§aFFA Lobby location has been set to ({$loc->getX()}, {$loc->getY()}, {$loc->getZ()})");
            }
        });
        $menu->setTitle("§4Manage FFA");
        $menu->setContent("Are you sure want to set ffa lobby as your current position");
        $menu->setButton1("§aContinue");
        $menu->setButton2("§cBack");
        $sender->sendForm($menu);
        return;
    }
}