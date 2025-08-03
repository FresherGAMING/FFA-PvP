<?php

namespace FFA;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use pocketmine\utils\Config;

class Scoreboard {

    public static function updateScoreboard(Player $player){
        $scoreboard_config = (new Config(Main::getInstance()->getDataFolder() . "config.yml"))->get("scoreboard");
        if(!$scoreboard_config["enabled"])return;
        $rop = RemoveObjectivePacket::create("Scoreboard-{$player->getName()}");
        $sdop = SetDisplayObjectivePacket::create(SetDisplayObjectivePacket::DISPLAY_SLOT_SIDEBAR, "Scoreboard-{$player->getName()}", $scoreboard_config["title"], "dummy", SetDisplayObjectivePacket::SORT_ORDER_ASCENDING);
        $player->getNetworkSession()->sendDataPacket($rop);
        $player->getNetworkSession()->sendDataPacket($sdop);
        FFA::getData($player->getName(), function($data)use($player, $scoreboard_config){
            $i = 0;
            $kdr = 0;
            $kills = 0;
            $deaths = 0;
            foreach($data as $d){
                $kills = $d["kills"];
                $deaths = $d["deaths"];
                if($d["kills"] === 0 || $d["deaths"] === 0){
                    $kdr = ($d["kills"] > 0) ? $d["kills"] : 0;
                } else {
                    $kdr = $d["kills"] / $d["deaths"];
                }
            }
            foreach($scoreboard_config["contents"] as $line => $text){
                $i++;
                if($i > 15)break;
                $char = chr(64 + $i);
                $text = str_replace(
                    ['{player_name}', '{kills}', '{deaths}', '{kdr}'],
                    [$player->getName(), $kills, $deaths, $kdr],
                    $text
                );
                self::newScoreboardLine($player, $line, "§{$char}§r" . $text);
            }
        });
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