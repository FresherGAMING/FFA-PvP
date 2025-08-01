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
        FFA::init();
        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);
        $this->getServer()->getCommandMap()->register("FFA", new ArenaCmd());
        $this->getServer()->getCommandMap()->register("FFA", new SpawnCmd());
    }

    public static function getInstance(){
        return self::$instance;
    }

    public static function updateScoreboard(Player $player){
        $rop = RemoveObjectivePacket::create("Scoreboard-{$player->getName()}");
        $sdop = SetDisplayObjectivePacket::create(SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR, "Scoreboard-{$player->getName()}", "§e§lYOUR STATS", "dummy", SetDisplayObjectivePacket::SORT_ORDER_ASCENDING);
        $player->getNetworkSession()->sendDataPacket($rop);
        $player->getNetworkSession()->sendDataPacket($sdop);
        FFA::getData($player->getName(), function($data)use($player){
            foreach($data as $d){
                self::newScoreboardLine($player, 1, "‎");
                self::newScoreboardLine($player, 2, "§a§lArena Stats");
                self::newScoreboardLine($player, 3, "§d♦ §4Kills: §c{$d["kills"]}");
                self::newScoreboardLine($player, 4, "§d♦ §4Deaths: §c{$d["deaths"]}");
                $kdr = 0;
                if($d["kills"] === 0 || $d["deaths"] === 0){
                    $kdr = ($d["kills"] > 0) ? $d["kills"] : 0;
                } else {
                    $kdr = $d["kills"] / $d["deaths"];
                }
                self::newScoreboardLine($player, 5, "§d♦ §4K/D Ratio: §c$kdr");
            }
        });
        Duels::getData($player->getName(), function($data)use($player){
            foreach($data as $d){
                self::newScoreboardLine($player, 6, "‎");
                self::newScoreboardLine($player, 7, "§a§lDuels Stats");
                self::newScoreboardLine($player, 8, "§d♦ §4Kills: §c{$d["kills"]}");
                self::newScoreboardLine($player, 9, "§d♦ §4Deaths: §c{$d["deaths"]}");
                $kdr = 0;
                if($d["kills"] === 0 || $d["deaths"] === 0){
                    $kdr = ($d["kills"] > 0) ? $d["kills"] : 0;
                } else {
                    $kdr = $d["kills"] / $d["deaths"];
                }
                self::newScoreboardLine($player, 10, "§d♦ §4K/D Ratio: §c$kdr");
            }
        });
        self::newScoreboardLine($player, 11, "§eserver.ip");
    }

    public static function newScoreboardLine(Player $player, int $line, string $msg){
        $spe = new ScorePacketEntry();
        $spe->scoreboardId = $line;
        $spe->objectiveName = "Scoreboard-{$player->getName()}";
        $spe->score = $line;
        $spe->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $spe->customName = $msg;
        $ssp = SetScorePacket::create(SetScorePacket::TYPE_CHANGE, [$spe]);
        $player->getNetworkSession()->sendDataPacket($ssp);
    }

}