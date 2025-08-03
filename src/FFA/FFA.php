<?php

namespace FFA;

use pocketmine\item\StringToItemParser;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
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

    public static Config $ffa;

    public static DataConnector $playerdata;

    public static array $players = [];

    public static array $arenas = [];

    public static function init(){
        self::$cfg = Main::getInstance()->getConfig();
        self::$ffa = (new Config(Main::getInstance()->getDataFolder() . "ffa.yml", Config::YAML, []));
        $database = (new Config(Main::getInstance()->getDataFolder() . "database.yml", Config::YAML, []))->get("database");
        self::$playerdata = libasynql::create(Main::getInstance(), $database, [
            "sqlite" => "ps-sqlite.sql",
            "mysql" => "ps-mysql.sql"
        ]);
        self::$playerdata->executeGeneric("data.setup");
        self::loadAllArena();
    }

    public static function setSpawn(Location $pos){
        $world = Server::getInstance()->getWorldManager()->getDefaultWorld()->getFolderName();
        if($pos->getWorld() instanceof World){
            $world = $pos->getWorld()->getFolderName();
        }
        $pos = [$pos->getX(), $pos->getY(), $pos->getZ(), $pos->getYaw(), $pos->getPitch()];
        $cfgpath = Main::getInstance()->getDataFolder() . "config.yml";
        $file = file($cfgpath);
        $stringpos = "[" . implode(", ", $pos) . "]";
        foreach($file as $line => $text){
            if(substr($text, 0, 13) === "  spawn-pos: "){
                $file[$line] = "  spawn-pos: " . $stringpos . PHP_EOL;
            }
            if(substr($text, 0, 15) === "  spawn-world: "){
                $file[$line] = "  spawn-world: " . $world . PHP_EOL;
            }
        }
        file_put_contents($cfgpath, implode($file));
        self::$cfg->reload();
    }

    public static function getSpawn(){
        $spawndata = self::$cfg->get("spawn-position");
        $spawnpos = $spawndata["spawn-pos"];
        $spawnworld = $spawndata["spawn-world"];
        if(empty($spawndata)){
            return null;
        }
        return new Location($spawnpos[0], $spawnpos[1], $spawnpos[2], Server::getInstance()->getWorldManager()->getWorldByName($spawnworld), $spawnpos[3] ?? 0, $spawnpos[4] ?? 0);
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
        $spawnitem = self::$cfg->get("spawn-item");
        if(!$spawnitem["enabled"])return;
        if($spawnitem["inv-clear"]){
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getOffHandInventory()->clearAll();
        }

        $item = StringToItemParser::getInstance()->parse($spawnitem["item-id"]);
        $item->setCustomName($spawnitem["item-name"] ?? $item->getVanillaName());
        $item->setLore([$spawnitem["item-lore"]]);
        $item->getNamedTag()->setString("ffa", "ffa");
        $player->getInventory()->setItem($spawnitem["inv-slot"] ?? 0, $item);
    }

    public static function loadAllArena(){
        $arenacfg = self::$ffa->get("arena");
        foreach($arenacfg as $arena){
            self::$arenas[$arena["id"]] = 
            new Arena($arena["id"], $arena["name"], $arena["description"], $arena["spawnpoint"],
            $arena["pos1"], $arena["pos2"], $arena["world"], $arena["kits"], 
            $arena["break"] ?? false, $arena["place"] ?? false, $arena["use-bucket"] ?? true);
        }
    }

    public static function loadArena(string $id){
        $arenacfg = self::$ffa->get("arena");
        foreach($arenacfg as $arena){
            if($arena["id"] !== $id)continue;
            self::$arenas[$arena["id"]] = 
            new Arena($arena["id"], $arena["name"], $arena["description"], $arena["spawnpoint"],
            $arena["pos1"], $arena["pos2"], $arena["world"], $arena["kits"], 
            $arena["break"] ?? false, $arena["place"] ?? false, $arena["use-bucket"] ?? true);
        }
    }

    public static function getAllArena(){
        return self::$arenas;
    }

    public static function addArena(string $name, string $displayName, 
    string $description, array $spawnPoints, array $pos1, array $pos2, 
    string $world, array $kits, bool $break, bool $place, bool $usebucket){
        $arenalist = self::$ffa->get("arena");
        $arenalist[] = [
            "id" => $name,
            "name" => $displayName, 
            "description" => $description,
            "pos1" => $pos1,
            "pos2" => $pos2,
            "world" => $world,
            "spawnpoint" => $spawnPoints,
            "kits" => $kits,
            "break" => $break,
            "place" => $place,
            "use-bucket" => $usebucket
        ];
        self::$ffa->set("arena", $arenalist);
        self::$ffa->save();
        self::loadArena($name);
    }

    public static function removeArena(string $name){
        $arenalist = self::$ffa->get("arena");
        foreach($arenalist as $k => $arena){
            if($arena["id"] !== $name)continue;
                unset($arenalist[$k]);
                self::$ffa->set("arena", $arenalist);
                self::$ffa->save();
            if(array_key_exists($name, self::$arenas)){
                unset(self::$arenas[$name]);
            }
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
                        Scoreboard::updateScoreboard($p);
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

    public static function getPlayerArena(Player $player){
        foreach(self::getAllArena() as $arena){
            if($arena->isInsideArena($player)){
                return $arena;
            }
        }
        return null;
    }

}