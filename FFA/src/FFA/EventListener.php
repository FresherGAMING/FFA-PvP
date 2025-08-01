<?php

namespace FFA;

use FFA\libs\jojoe77777\FormAPI\SimpleForm;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;

class EventListener implements Listener {

    public function onJoin(PlayerJoinEvent $event){
        FFA::loadPlayerData($event->getPlayer()->getName());
        FFA::teleportToSpawn($event->getPlayer());
        FFA::updateScoreboard($event->getPlayer());
    }

    public function onUse(PlayerItemUseEvent $event){
        $player = $event->getPlayer();
        if($event->getItem()->getNamedTag()->getTag("ffa")){
            $menu = new SimpleForm(function($player, $data){
                if($data === null)return;
                FFA::teleportToArena($player, $data);
            });
            $menu->setTitle("§aFFA Menu");
            if(empty(FFA::getArenas())){
                $menu->setContent("§cThere is no available arena");
            }
            foreach(FFA::getArenas() as $id => $arena){
                $menu->addButton($arena->getDisplayName() . "\n§8Tap to teleport", -1, '', $id);
            }
            $player->sendForm($menu);
        }
        if($event->getItem()->getNamedTag()->getTag("duels")){
            $menu = new SimpleForm(function($player, $data){
                if($data === null)return;
                Duels::teleportToArena($player, $data);
            });
            $menu->setTitle("§aDuels Menu");
            if(empty(Duels::getArenas())){
                $menu->setContent("§cThere is no available arena");
            }
            foreach(Duels::getArenas() as $id => $arena){
                $menu->addButton($arena->getDisplayName() . "\n§8Tap to teleport", -1, '', $id);
            }
            $player->sendForm($menu);
        }
    }

    public function onDeath(PlayerDeathEvent $event){
        $player = $event->getPlayer();
        $lastdamagecause = $player->getLastDamageCause();
        FFA::addDeaths($player->getName(), 1);
        if($lastdamagecause instanceof EntityDamageByEntityEvent){
            $entity = $lastdamagecause->getDamager();
            if($entity instanceof Player){
                FFA::addKills($entity->getName(), 1);
            }
        }
        FFA::teleportToSpawn($player);
    }

}