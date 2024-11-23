<?php

namespace MyEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scoreboard\Scoreboard;

class Main extends PluginBase implements Listener {

    private $balances = [];
    private $scoreboards = [];
    private $config;

    public function onEnable(): void {
        $this->getLogger()->info("MyEconomy aktiviert!");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Lade oder erstelle die Konfigurationsdatei
        $this->config = new Config($this->getDataFolder() . "balances.yml", Config::YAML);
        $this->balances = $this->config->getAll();

        // Starte den Belohnungs-Timer
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (): void {
            foreach ($this->getServer()->getOnlinePlayers() as $player) {
                $name = $player->getName();
                $this->balances[$name] = ($this->balances[$name] ?? 0) + 500;
                $player->sendMessage(TextFormat::GREEN . "Du hast 500 $ erhalten!");
                $this->updateScoreboard($player);
            }
        }), 20 * 60 * 10); // alle 10 Minuten
    }

    public function onDisable(): void {
        // Speichere die Kontostände beim Ausschalten des Servers
        $this->config->setAll($this->balances);
        $this->config->save();
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        // Stelle sicher, dass der Spieler ein Konto hat
        if (!isset($this->balances[$name])) {
            $this->balances[$name] = 100; // Startgeld für neue Spieler
        }

        // Erstelle das Scoreboard für den Spieler
        $this->setScoreboard($player);
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        // Speichere den Kontostand in der Konfigurationsdatei
        $this->config->set($name, $this->balances[$name]);
        $this->config->save();
    }

    public function setScoreboard(Player $player): void {
        $name = $player->getName();
        $balance = $this->balances[$name] ?? 0;

        // Erstelle oder aktualisiere das Scoreboard
        if (!isset($this->scoreboards[$name])) {
            $this->scoreboards[$name] = new Scoreboard();
        }

        $scoreboard = $this->scoreboards[$name];
        $scoreboard->setDisplayName(TextFormat::GOLD . "Dein Kontostand");
        $scoreboard->addEntry("Coins: " . TextFormat::YELLOW . $balance, 1);
        $scoreboard->send($player);
    }

    public function updateScoreboard(Player $player): void {
        $name = $player->getName();
        $balance = $this->balances[$name] ?? 0;

        if (isset($this->scoreboards[$name])) {
            $scoreboard = $this->scoreboards[$name];
            $scoreboard->updateEntry("Coins: " . TextFormat::YELLOW . $balance, 1);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($sender instanceof Player) {
            $name = $sender->getName();

            if ($command->getName() === "money") {
                $balance = $this->balances[$name] ?? 0;
                $sender->sendMessage("Dein Kontostand: $balance Coins");
                return true;
            }

            if ($command->getName() === "pay") {
                if (isset($args[0], $args[1])) {
                    $targetName = $args[0];
                    $amount = (int)$args[1];

                    if ($amount > 0 && isset($this->balances[$name]) && $this->balances[$name] >= $amount) {
                        $this->balances[$name] -= $amount;
                        $this->balances[$targetName] = ($this->balances[$targetName] ?? 0) + $amount;

                        $sender->sendMessage("Du hast $amount Coins an $targetName gesendet!");
                        
                        // Aktualisiere das Scoreboard für den Absender und Empfänger
                        $this->updateScoreboard($sender);
                        $target = $this->getServer()->getPlayerExact($targetName);
                        if ($target !== null) {
                            $this->updateScoreboard($target);
                        }
                    } else {
                        $sender->sendMessage("Ungültige Transaktion!");
                    }
                }
                return true;
            }
        }

        return false;
    }
}
