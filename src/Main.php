  }
}
<?php

namespace PocketEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;

class Main extends PluginBase implements Listener {

    private $economy;
    private $debt;

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();

        $this->economy = new Config($this->getDataFolder() . "economy.yml", Config::YAML, []);
        $this->debt = new Config($this->getDataFolder() . "debt.yml", Config::YAML, []);

        $this->getLogger()->info("PocketEconomy has been enabled");
    }

    public function onDisable(): void {
        $this->getLogger()->info("PocketEconomy has been disabled");
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        if (!$this->economy->exists($name)) {
            $this->economy->set($name, 1000); // Set default balance
            $this->economy->save();
        }

        if (!$this->debt->exists($name)) {
            $this->debt->set($name, 0); // Set default debt
            $this->debt->save();
        }
    }

    public function getBalance(string $playerName): int {
        return $this->economy->get($playerName, 0);
    }

    public function addBalance(string $playerName, int $amount): void {
        $balance = $this->getBalance($playerName);
        $balance += $amount;
        $this->economy->set($playerName, $balance);
        $this->economy->save();
    }

    public function setBalance(string $playerName, int $amount): void {
        $this->economy->set($playerName, $amount);
        $this->economy->save();
    }

    public function reduceBalance(string $playerName, int $amount): void {
        $balance = $this->getBalance($playerName);
        $balance -= $amount;
        if ($balance < 0) $balance = 0;
        $this->economy->set($playerName, $balance);
        $this->economy->save();
    }

    public function getDebt(string $playerName): int {
        return $this->debt->get($playerName, 0);
    }

    public function addDebt(string $playerName, int $amount): void {
        $debt = $this->getDebt($playerName);
        $debt += $amount;
        $this->debt->set($playerName, $debt);
        $this->debt->save();
    }

    public function reduceDebt(string $playerName, int $amount): void {
        $debt = $this->getDebt($playerName);
        $debt -= $amount;
        if ($debt < 0) $debt = 0;
        $this->debt->set($playerName, $debt);
        $this->debt->save();
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            if ($command->getName() === "moneysave") {
                $this->economy->save();
                $this->debt->save();
                $sender->sendMessage("Data saved successfully.");
                return true;
            } elseif ($command->getName() === "moneyload") {
                $this->economy->reload();
                $this->debt->reload();
                $sender->sendMessage("Data loaded successfully.");
                return true;
            } else {
                $sender->sendMessage("This command can only be used in-game.");
                return false;
            }
        }

        $player = $sender->getName();

        switch ($command->getName()) {
            case "mymoney":
                $balance = $this->getBalance($player);
                $sender->sendMessage("Your balance is $balance.");
                break;

            case "mydebt":
                $debt = $this->getDebt($player);
                $sender->sendMessage("Your debt is $debt.");
                break;

            case "takedebt":
                if (count($args) < 1) {
                    $sender->sendMessage("Usage: /takedebt <amount>");
                    return false;
                }
                $amount = intval($args[0]);
                if ($amount < 0) {
                    $sender->sendMessage("Amount must be a non-negative number.");
                    return false;
                }
                $this->addDebt($player, $amount);
                $this->addBalance($player, $amount);
                $sender->sendMessage("You borrowed $amount. Your new debt is " . $this->getDebt($player) . ".");
                break;

            case "returndebt":
                if (count($args) < 1) {
                    $sender->sendMessage("Usage: /returndebt <amount>");
                    return false;
                }
                $amount = intval($args[0]);
                if ($amount < 0) {
                    $sender->sendMessage("Amount must be a non-negative number.");
                    return false;
                }
                $currentDebt = $this->getDebt($player);
                if ($amount > $currentDebt) {
                    $sender->sendMessage("You can't return more than your current debt.");
                    return false;
                }
                $this->reduceDebt($player, $amount);
                $this->reduceBalance($player, $amount);
                $sender->sendMessage("You returned $amount. Your remaining debt is " . $this->getDebt($player) . ".");
                break;

            case "topmoney":
                // Assuming a simple top money implementation
                arsort($balances = $this->economy->getAll());
                $top = array_slice($balances, 0, 10, true);
                $message = "Top money:\n";
                foreach ($top as $name => $balance) {
                    $message .= "$name: $balance\n";
                }
                $sender->sendMessage($message);
                break;

            case "setmoney":
                if (!$sender->hasPermission("pocketeconomy.command.setmoney")) {
                    $sender->sendMessage("You do not have permission to use this command.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("Usage: /setmoney <player> <money>");
                    return false;
                }
                $target = $args[0];
                $amount = intval($args[1]);
                if ($amount < 0) {
                    $sender->sendMessage("Amount must be a non-negative number.");
                    return false;
                }
                $this->setBalance($target, $amount);
                $sender->sendMessage("Set $target's balance to $amount.");
                break;

            case "givemoney":
                if (!$sender->hasPermission("pocketeconomy.command.givemoney")) {
                    $sender->sendMessage("You do not have permission to use this command.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("Usage: /givemoney <player> <money>");
                    return false;
                }
                $target = $args[0];
                $amount = intval($args[1]);
                if ($amount < 0) {
                    $sender->sendMessage("Amount must be a non-negative number.");
                    return false;
                }
                $this->addBalance($target, $amount);
                $sender->sendMessage("Gave $amount to $target.");
                break;

            case "takemoney":
                if (!$sender->hasPermission("pocketeconomy.command.takemoney")) {
                    $sender->sendMessage("You do not have permission to use this command.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("Usage: /takemoney <player> <money>");
                    return false;
                }
                $target = $args[0];
                $amount = intval($args[1]);
                if ($amount < 0) {
                    $sender->sendMessage("Amount must be a non-negative number.");
                    return false;
                }
                $this->reduceBalance($target, $amount);
                $sender->sendMessage("Took $amount from $target.");
                break;

            case "seemoney":
                if (count($args) < 1) {
                    $sender->sendMessage("Usage: /seemoney <player>");
                    return false;
                }
                $target = $args[0];
                $balance = $this->getBalance($target);
                $sender->sendMessage("$target's balance is $balance.");
            break;

        case "bank":
            if (count($args) < 1) {
                $sender->sendMessage("Usage: /bank deposit <money> | /bank withdraw <money> | /bank mymoney");
                return false;
            }
            $action = $args[0];
            if ($action === "deposit" || $action === "withdraw") {
                if (count($args) < 2) {
                    $sender->sendMessage("Usage: /bank $action <money>");
                    return false;
                }
                $amount = intval($args[1]);
                if ($amount < 0) {
                    $sender->sendMessage("Amount must be a non-negative number.");
                    return false;
                }
                switch ($action) {
                    case "deposit":
                        $balance = $this->getBalance($player);
                        if ($amount > $balance) {
                            $sender->sendMessage("You can't deposit more than your current balance.");
                            return false;
                        }
                        $this->reduceBalance($player, $amount);
                        $this->addBalance("bank-$player", $amount);
                        $sender->sendMessage("Deposited $amount to your bank account.");
                        break;

                    case "withdraw":
                        $bankBalance = $this->getBalance("bank-$player");
                        if ($amount > $bankBalance) {
                            $sender->sendMessage("You can't withdraw more than your bank balance.");
                            return false;
                        }
                        $this->reduceBalance("bank-$player", $amount);
                        $this->addBalance($player, $amount);
                        $sender->sendMessage("Withdrew $amount from your bank account.");
                        break;
                }
            } else if ($action === "mymoney") {
                $bankBalance = $this->getBalance("bank-$player");
                $sender->sendMessage("Your bank balance is $bankBalance.");
            } else {
                $sender->sendMessage("Usage: /bank deposit <money> | /bank withdraw <money> | /bank mymoney");
            }
            break;

        case "mystatus":
            $balance = $this->getBalance($player);
            $debt = $this->getDebt($player);
            $sender->sendMessage("Your balance: $balance\nYour debt: $debt");
            break;

        case "bankadmin":
            if (!$sender->hasPermission("pocketeconomy.command.bankadmin")) {
                $sender->sendMessage("You do not have permission to use this command.");
                return true;
            }
            if (count($args) < 3) {
                $sender->sendMessage("Usage: /bankadmin takemoney <player> <money> | /bankadmin givemoney <player> <money>");
                return false;
            }
            $action = $args[0];
            $target = $args[1];
            $amount = intval($args[2]);
            if ($amount < 0) {
                $sender->sendMessage("Amount must be a non-negative number.");
                return false;
            }
            switch ($action) {
                case "takemoney":
                    $bankBalance = $this->getBalance("bank-$target");
                    if ($amount > $bankBalance) {
                        $sender->sendMessage("You can't take more than $target's bank balance.");
                        return false;
                    }
                    $this->reduceBalance("bank-$target", $amount);
                    $sender->sendMessage("Took $amount from $target's bank account.");
                    break;

                case "givemoney":
                    $this->addBalance("bank-$target", $amount);
                    $sender->sendMessage("Gave $amount to $target's bank account.");
                    break;

                default:
                    $sender->sendMessage("Usage: /bankadmin takemoney <player> <money> | /bankadmin givemoney <player> <money>");
                    break;
            }
            break;

        case "economys":
            $plugins = $this->getServer()->getPluginManager()->getPlugins();
            $economyPlugins = [];
            foreach ($plugins as $plugin) {
                if ($plugin instanceof PluginBase) {
                    if (method_exists($plugin, "getEconomy")) {
                        $economyPlugins[] = $plugin->getDescription()->getName();
                    }
                }
            }
            $sender->sendMessage("Economy plugins: " . implode(", ", $economyPlugins));
            break;

        default:
            return false;
    }
    return true;
}
}
