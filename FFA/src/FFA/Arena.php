<?php

namespace FFA;

use pocketmine\entity\Location;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

class Arena {
    
    private array $kits;

    public function __construct(private string $name, private string $displayName, private array $spawnPoints, array $kits){
        $this->loadKits($kits);
    }

    public function getName(){
        return $this->name;
    }

    public function getDisplayName(){
        return $this->displayName;
    }

    public function getRandomSpawnPoint() : ?Location{
        if(empty($this->spawnPoints)){
            return null;
        }
        $spawndata = $this->spawnPoints[array_rand($this->spawnPoints)];
        return new Location($spawndata[0], $spawndata[1], $spawndata[2], Server::getInstance()->getWorldManager()->getWorldByName($spawndata[5]), $spawndata[3], $spawndata[4]);
    }

    public function getSpawnpoints(){
        return $this->spawnPoints;
    }

    public function addSpawnPoint(Location $pos){
        $this->spawnPoints[] = [$pos->getX(), $pos->getY(), $pos->getZ(), $pos->getYaw(), $pos->getPitch(), $pos->getWorld()->getFolderName()];
        $arenalist = FFA::$cfg->get("arenas");
        $arenalist[$this->name]["spawnpoint"][] = [$pos->getX(), $pos->getY(), $pos->getZ(), $pos->getYaw(), $pos->getPitch(), $pos->getWorld()->getFolderName()];
        FFA::$cfg->set("arenas", $arenalist);
        FFA::$cfg->save();
    }

    public function removeSpawnPoint(int $id){
        if(array_key_exists($id, $this->spawnPoints)){
            unset($this->spawnPoints[$id]);
            $this->spawnPoints = array_values($this->spawnPoints);
        }
        $arenalist = FFA::$cfg->get("arenas");
        if(array_key_exists($id, $arenalist[$this->name]["spawnpoint"])){
            unset($arenalist[$this->name]["spawnpoint"][$id]);
            FFA::$cfg->set("arenas", $arenalist);
            FFA::$cfg->save();
        }
    }

    public function setKit(Player $p){
        $kit = $p->getInventory()->getContents();
        foreach($p->getArmorInventory()->getContents() as $slot => $item){
            $kit[str_replace([0, 1, 2, 3], ["helmet", "chestplate", "leggings", "boots"], $slot)] = $item;
        }
        foreach($p->getOffHandInventory()->getContents() as $slot => $item){
            $kit[str_replace([0], ["offhand"], $slot)] = $item;
        }
        $this->kits = $kit;
        $arenalist = FFA::$cfg->get("arenas");
        $serializedkit = [];
        foreach($kit as $slot => $item){
            $serializedkit[$slot] = StringToItemParser::getInstance()->lookupAliases($item)[0] . "#--$909$--#" . $item->getCount() . "#--$909$--#" . serialize($item->getNamedTag());
        }
        $arenalist[$this->name]["kits"] = $serializedkit;
        FFA::$cfg->set("arenas", $arenalist);
        FFA::$cfg->save();
    }

    public function loadKits(array $kits){
        foreach($kits as $slot => $data){
            $itemid = explode("#--$909$--#", $data)[0];
            $count = explode("#--$909$--#", $data)[1];
            $tags = unserialize(explode("#--$909$--#", $data)[2]);
            $this->kits[$slot] = StringToItemParser::getInstance()->parse($itemid)->setCount($count)->setNamedTag($tags);
        }
    }

    public function giveKit(Player $player){
        $inv = $player->getInventory();
        $inv->clearAll();
        if(empty($this->kits)){
            $player->sendMessage("§cError: There is no kit set for this arena");
            return;
        }
        foreach($this->kits as $slot => $item){
            match($slot){
                "helmet" => $player->getArmorInventory()->setHelmet($item),
                "chestplate" => $player->getArmorInventory()->setChestplate($item),
                "leggings" => $player->getArmorInventory()->setLeggings($item),
                "boots" => $player->getArmorInventory()->setBoots($item),
                "offhand" => $player->getOffHandInventory()->setItem(0, $item),
                default => $inv->setItem($slot, $item)
            };
        }
    }

    public function setDisplayName(string $displayName){
        $this->displayName = $displayName;
        $arenalist = FFA::$cfg->get("arenas");
        $arenalist[$this->name]["displayname"] = $displayName;
        FFA::$cfg->set("arenas", $arenalist);
        FFA::$cfg->save();
    }
}