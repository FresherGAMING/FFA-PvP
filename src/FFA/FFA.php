<?php

namespace FFA;

use FFA\libs\poggit\libasynql\DataConnector;
use FFA\libs\poggit\libasynql\libasynql;
use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use pocketmine\world\World;

class FFA {

    public static Config $cfg;

    public static DataConnector $playerdata;

    public static array $players = [];

    public static array $arenas = [];

    public static function init(){
        self::$cfg = new Config(Main::getInstance()->getDataFolder() . "config.yml", Config::YAML, [
            "spawn" => [],
            "arenas" => []
        ]);
        self::$playerdata = libasynql::create(Main::getInstance(), 
        ["type" => "sqlite",
            "sqlite" => [
                "file" => "ffa-playerdata.sqlite"
            ],
            "mysql" => [
                "host" => "127.0.0.1",
                "username" => "root",
                "password" => "",
                "schema" => "your_schema"
            ],
            "worker-limit" => 1], [
                "sqlite" => "ps.sql",
                "mysql" => "ps.sql"
            ]);
        self::$playerdata->executeGeneric("data.setup");
        self::loadArenas();
    }

    public static function setSpawn(Location $pos){
        $data = [$pos->getX(), $pos->getY(), $pos->getZ(), $pos->getYaw(), $pos->getPitch()];
        if($pos->getWorld() instanceof World){
            $data[] = $pos->getWorld()->getFolderName();
        } else {
            $data[] = Server::getInstance()->getWorldManager()->getDefaultWorld()->getFolderName();
        }
        self::$cfg->set("spawn", $data);
        self::$cfg->save();
    }

    public static function getSpawn(){
        $spawndata = self::$cfg->get("spawn");
        if(empty($spawndata)){
            return null;
        }
        return new Location($spawndata[0], $spawndata[1], $spawndata[2], Server::getInstance()->getWorldManager()->getWorldByName($spawndata[5]), $spawndata[3], $spawndata[4]);
    }

    public static function teleportToSpawn(Player $player){
        if($player->isOnline()){
            if(!self::getSpawn()){
                $player->sendMessage("§cError: No spawnpoint set for FFA Lobby");
                return;
            }
            $player->teleport(self::getSpawn());
            self::giveSpawnItem($player);
            $player->sendMessage("§aTeleporting you to spawn...");
        }
    }    
    public static function giveSpawnItem(Player $player){
        $item = VanillaItems::DIAMOND_SWORD();
        $item->setCustomName("§r§bFFA Menu");
        $item->setLore(["§r§aClick to open FFA menu"]);
        $item->getNamedTag()->setString("ffa", "ffa");
        $item2 = VanillaItems::DIAMOND_SWORD();
        $item2->setCustomName("§r§aDuels Menu");
        $item2->setLore(["§r§aClick to open Duels menu"]);
        $item2->getNamedTag()->setString("duels", "duels");
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getOffHandInventory()->clearAll();
        $player->getInventory()->setItem(0, $item);
        $player->getInventory()->setItem(1, $item2);
    }

    public static function loadArenas(){
        foreach(self::$cfg->get("arenas") as $id => $arena){
            self::$arenas[$id] = new Arena($id, $arena["displayname"], $arena["spawnpoint"], $arena["kits"]);
        }
    }

    public static function getArenas(){
        return self::$arenas;
    }

    public static function addArena(string $name, string $displayName, array $spawnPoints, array $kits){
        $arenalist = self::$cfg->get("arenas");
        $arenalist[$name] = [
            "displayname" => $displayName,
            "spawnpoint" => $spawnPoints, 
            "kits" => $kits
        ];
        self::$cfg->set("arenas", $arenalist);
        self::$cfg->save();
        self::$arenas[$name] = new Arena($name, $displayName, $spawnPoints, $kits);
    }

    public static function removeArena(string $name){
        $arenalist = self::$cfg->get("arenas");
        if(array_key_exists($name, $arenalist)){
            unset($arenalist[$name]);
            self::$cfg->set("arenas", $arenalist);
            self::$cfg->save();
        }
        if(array_key_exists($name, self::$arenas)){
            unset(self::$arenas[$name]);
        }
    }

    public static function teleportToArena(Player $player, string $arena){
        if(array_key_exists($arena, self::$arenas)){
            $pos = self::$arenas[$arena]->getRandomSpawnPoint();
            if($pos === null){
                $player->sendMessage("§cError: There is no spawnpoint set for arena '$arena'");
                return;
            }
            $player->teleport($pos, $pos->getYaw(), $pos->getPitch());
            self::$arenas[$arena]->giveKit($player);
        } else {
            $player->sendMessage("§cError: Arena '$arena' not found!");
        }
    }

    public static function getArena(string $id) : ?Arena{
        if(array_key_exists($id, self::$arenas)){
            return self::$arenas[$id];
        }
        return null;
    }

    public static function getData(string $player, \Closure $closure){
        self::$playerdata->executeSelect("data.getdata", ["name" => $player], function($data)use($closure){
            $closure($data);
        });
    }

    public static function updateData(string $player){
        self::getData($player, function($data)use($player){
            foreach($data as $d){
                if(array_key_exists($d["name"], FFA::$players)){
                    unset($d["name"]);
                    FFA::$players[$player] = $data;
                    $p = Server::getInstance()->getPlayerExact($player);
                    if($p instanceof Player && $p->isOnline()){
                        self::updateScoreboard($p);
                    }
                }
            }
        });
    }

    public static function setKills(string $player, int $kills){
        self::$playerdata->executeChange("data.setkills", ["name" => $player, "kills" => $kills], function($success)use($player){
            FFA::updateData($player);
        });
    }

    public static function setDeaths(string $player, int $deaths){
        self::$playerdata->executeChange("data.setdeaths", ["name" => $player, "deaths" => $deaths], function($success)use($player){
            FFA::updateData($player);
        });
    }

    public static function addKills(string $player, int $amount){
        self::getData($player, function($data)use($player, $amount){
            foreach($data as $d){
                self::setKills($player, $d["kills"] + $amount);
            }
        });
    }

    public static function reduceKills(string $player, int $amount){
        self::getData($player, function($data)use($player, $amount){
            foreach($data as $d){
                self::setKills($player, ($d["kills"] - $amount) < 0 ? 0 : ($d["kills"] - $amount));
            }
        });
    }

    public static function addDeaths(string $player, int $amount){
        self::getData($player, function($data)use($player, $amount){
            foreach($data as $d){
                self::setDeaths($player, $d["deaths"] + $amount);
            }
        });
    }

    public static function reduceDeaths(string $player, int $amount){
        self::getData($player, function($data)use($player, $amount){
            foreach($data as $d){
                self::setDeaths($player, ($d["deaths"] - $amount) < 0 ? 0 : ($d["deaths"] - $amount));
            }
        });
    }

    public static function loadPlayerData(string $player){
        self::getData($player, function($data)use($player){
            if(empty($data)){
                FFA::$playerdata->executeInsert("data.addplayer", ["name" => $player, "kills" => 0, "deaths" => 0]);
                FFA::$players[$player] = ["kills" => 0, "deaths" => 0];
                return;
            }
            foreach($data as $d){
                FFA::$players[$player] = ["kills" => $d["kills"], "deaths" => $d["deaths"]];
                return;
            }
        });
    }

}