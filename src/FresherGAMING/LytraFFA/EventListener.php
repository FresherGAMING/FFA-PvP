<?php

namespace FresherGAMING\LytraFFA;

use jojoe77777\FormAPI\SimpleForm;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

class EventListener implements Listener {

    public function onJoin(PlayerJoinEvent $event){
        FFA::loadPlayerData($event->getPlayer()->getName());
        if(FFA::$cfg->get("spawn-position")["enabled"]){
            FFA::teleportToSpawn($event->getPlayer());
        }
        Scoreboard::updateScoreboard($event->getPlayer());        
    }

    public function onUse(PlayerItemUseEvent $event){
        $player = $event->getPlayer();
        if($event->getItem()->getNamedTag()->getTag("ffa")){
            $menu = new SimpleForm(function($player, $data){
                if($data === null)return;
                FFA::teleportToArena($player, $data);
            });
            $cfg = FFA::$cfg->get("spawn-item")["spawn-item-ui"];
            $menu->setTitle($cfg["ui-title"] ?? "§aFFA Menu");
            if(empty(FFA::getAllArena())){
                $menu->setContent("§cThere is no available arena");
            }
            foreach(FFA::getAllArena() as $id => $arena){
                $desc = $arena->getDescription();
                $default = $arena->getDisplayName() . ($desc !== "" ? "\n$desc" : "");
                $format = str_replace(
                    ["{arena_name}", "{arena_id}", "{arena_desc}", "{arena_player_amount}"],
                    [$arena->getDisplayName(), $arena->getName(), $arena->getDescription(), $arena->getPlayerAmount()],
                    $cfg["ui-button"] ?? $default
                );
                $menu->addButton($format, -1, '', $id);
            }
            $player->sendForm($menu);
        }
    }

    public function onDeath(PlayerDeathEvent $event){
        $player = $event->getPlayer();
        foreach(FFA::getAllArena() as $arena){
            if($arena->isInsideArena($player)){
                $lastdamagecause = $player->getLastDamageCause();
                FFA::addDeaths($player->getName(), 1);
                if($lastdamagecause instanceof EntityDamageByEntityEvent){
                    $entity = $lastdamagecause->getDamager();
                    if($entity instanceof Player){
                        FFA::addKills($entity->getName(), 1);
                    }
                }
            }
        }
    }

    public function onRespawn(PlayerRespawnEvent $event){
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function()use($event){
            FFA::teleportToSpawn($event->getPlayer());
        }), 2);
    }

    public function onBreak(BlockBreakEvent $event){
        $arena = FFA::getPlayerArena($event->getPlayer());
        if($arena && (!$arena->getPerm("break"))){
            $event->cancel();
            return;
        }
    }

    public function onPlace(BlockPlaceEvent $event){
        $arena = FFA::getPlayerArena($event->getPlayer());
        if($arena && (!$arena->getPerm("place"))){
            $event->cancel();
            return;
        }
    }

    public function onBucket(PlayerBucketEvent $event){
        $arena = FFA::getPlayerArena($event->getPlayer());
        if($arena && (!$arena->getPerm("usebucket"))){
            $event->cancel();
            return;
        }
    }

}