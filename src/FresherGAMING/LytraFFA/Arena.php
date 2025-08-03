<?php

namespace FresherGAMING\LytraFFA;

use pocketmine\entity\Location;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

class Arena {

    private array $kits = [];

    public function __construct(private string $name, private string $displayName, 
        private string $description, private array $spawnPoints, 
        private array $pos1, private array $pos2, private string $world, array $kits, 
        private bool $break, private bool $place, private bool $usebucket){
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
        return new Location($spawndata[0], $spawndata[1], $spawndata[2], Server::getInstance()->getWorldManager()->getWorldByName($this->world), $spawndata[3]  ?? 0, $spawndata[4] ?? 0);
    }

    public function getSpawnpoints(){
        return $this->spawnPoints;
    }

    public function addSpawnPoint(Location $pos){
        $this->spawnPoints[] = [$pos->getX(), $pos->getY(), $pos->getZ(), $pos->getYaw(), $pos->getPitch(), $pos->getWorld()->getFolderName()];
        $arenalist = FFA::$ffa->get("arena");
        $arenalist[$this->getId_Int()]["spawnpoint"][] = [$pos->getX(), $pos->getY(), $pos->getZ(), $pos->getYaw(), $pos->getPitch(), $pos->getWorld()->getFolderName()];
        FFA::$ffa->set("arena", $arenalist);
        FFA::$ffa->save();
    }

    public function removeSpawnPoint(int $id){
        if(array_key_exists($id, $this->spawnPoints)){
            unset($this->spawnPoints[$id]);
            $this->spawnPoints = array_values($this->spawnPoints);
        }
        $arenalist = FFA::$ffa->get("arena");
        $id_int = $this->getId_Int();
        if(array_key_exists($id, $arenalist[$id_int]["spawnpoint"])){
            unset($arenalist[$id_int]["spawnpoint"][$id]);
            FFA::$ffa->set("arena", $arenalist);
            FFA::$ffa->save();
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
        $arenalist = FFA::$ffa->get("arena");
        $serializedkit = [];
        foreach($kit as $slot => $item){
            $serializedkit[$slot] = [StringToItemParser::getInstance()->lookupAliases($item)[0], $item->getCount(), serialize($item->getNamedTag())];
        }
        $arenalist[$this->getId_Int()]["kits"] = $serializedkit;
        FFA::$ffa->set("arena", $arenalist);
        FFA::$ffa->save();
    }

    public function loadKits(array $kits){
        foreach($kits as $slot => $data){
            $itemid = $data[0];
            $count = $data[1];
            $tags = unserialize($data[2]);
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
        $arenalist = FFA::$ffa->get("arena");
        $arenalist[$this->getId_Int()]["name"] = $displayName;
        FFA::$ffa->set("arena", $arenalist);
        FFA::$ffa->save();
    }

    public function getDescription(){
        return $this->description;
    }

    public function setDescription(string $desc){
        $this->description = $desc;
        $arenalist = FFA::$ffa->get("arena");
        $arenalist[$this->getId_Int()]["description"] = $desc;
        FFA::$ffa->set("arena", $arenalist);
        FFA::$ffa->save();
    }

    public function getPerm(string $perm){
        return $this->{$perm};
    }

    public function setPerm(string $perm, bool $toggle){
        $this->{str_replace("-", "", $perm)} = $toggle;
        $arenalist = FFA::$ffa->get("arena");
        $arenalist[$this->getId_Int()][$perm] = $toggle;
        FFA::$ffa->set("arena", $arenalist);
        FFA::$ffa->save();
    }

    public function setPos(int $pos_id, Position $pos){
        $this->{"pos$pos_id"} = [$pos->getX(), $pos->getY(), $pos->getZ()];
        $arenalist = FFA::$ffa->get("arena");
        $arenalist[$this->getId_Int()]["pos$pos_id"] = [$pos->getX(), $pos->getY(), $pos->getZ()];
        FFA::$ffa->set("arena", $arenalist);
        FFA::$ffa->save();
    }

    public function getId_Int(){
        $arenalist = FFA::$ffa->get("arena");
        foreach($arenalist as $i => $arena){
            if($arena["id"] === $this->name){
                return $i;
            }
        }
    }

    public function getPlayerAmount(){
        $count = 0;
        foreach(Server::getInstance()->getOnlinePlayers() as $p){
            if($this->isInsideArena($p)){
                $count++;
            }
        }
        return $count;
    }

    public function isInsideArena(Player $p){
        $pos1 = $this->pos1;
        $pos2 = $this->pos2;
        $xMin = min([$pos1[0], $pos2[0]]);
        $xMax = max([$pos1[0], $pos2[0]]);
        $yMin = min([$pos1[1], $pos2[1]]);
        $yMax = max([$pos1[1], $pos2[1]]);
        $zMin = min([$pos1[2], $pos2[2]]);
        $zMax = max([$pos1[2], $pos2[2]]);
        $pos = $p->getPosition();
        [$x, $y, $z] = [$pos->getX(), $pos->getY(), $pos->getZ()];
        if(
            ($p->getWorld()->getFolderName() === $this->world) &&
            ($x >= $xMin && $x <= $xMax) &&
            ($y >= $yMin && $y <= $yMax) &&
            ($z >= $zMin && $z <= $zMax)
        ){
            return true;
        }
        return false;
    }
}