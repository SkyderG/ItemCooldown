<?php

namespace ItemCooldown;

use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;

class Loader extends PluginBase implements Listener
{
    public array $cooldown = [];

    protected function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->saveDefaultConfig();

        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            public function __construct(
                protected Loader $plugin
            )
            {
            }

            public function onRun(): void
            {
                if (empty($this->plugin->cooldown)) return;

                foreach ($this->plugin->cooldown as $name => $data) {
                    foreach ($data as $id => $time) {
                        if (time() >= $time) unset($this->plugin->cooldown[$name]);
                    }
                }
            }
        }, 20);
    }

    public function onConsume(PlayerItemConsumeEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();

        $config = $this->getConfig();

        $itemString = $item->getId() . ":" . $item->getMeta();
        if (($time = $config->get("consumables")[$itemString]) !== null) {
            if (isset($this->cooldown[$player->getName()][$itemString])) {
                $player->sendMessage($config->get("message", "§cIn cooldown."));
                $event->cancel();
                return;
            }

            $this->cooldown[$player->getName()][$itemString] = time() + $time;
            $event->uncancel();
        }
    }

    public function onLaunch(ProjectileLaunchEvent $event)
    {
        $entity = $event->getEntity();
        $player = $entity->getOwningEntity();

        if ($player instanceof Player) {
            $itemString = $entity::getNetworkTypeId();
            $config = $this->getConfig();

            if (($time = $config->get("throwables")[$itemString]) !== null) {
                if (isset($this->cooldown[$player->getName()][$itemString])) {
                    $player->sendMessage($config->get("message", "§cIn cooldown."));
                    $event->cancel();
                    return;
                }

                $this->cooldown[$player->getName()][$itemString] = time() + $time;
                $event->uncancel();
            }
        }
    }
}
