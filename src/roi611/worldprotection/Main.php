<?php
    
namespace roi611\worldprotection;
    
use pocketmine\plugin\PluginBase;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\player\Player;

use pocketmine\item\LiquidBucket;
use pocketmine\item\ItemBlock;

use pocketmine\utils\Config;

use pocketmine\Server;
    
class Main extends PluginBase implements Listener {
    
    private $place;
    private $idplace;
    private $cantplace;
    
    private $break;
    private $idbreak;
    private $cantbreak;

    private $cantuse;

    public function onEnable():void {
       
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        if(file_exists($this->getDataFolder()."place.yml")){
            if(rename($this->getDataFolder()."place.yml",$this->getDataFolder()."Protected_Place_World.yml")){
                $this->getLogger()->info("§9place.yml§e を最新バージョンに対応させました。");
            }
        }

        if(file_exists($this->getDataFolder()."break.yml")){
            if(rename($this->getDataFolder()."break.yml",$this->getDataFolder()."Protected_Break_World.yml")){
                $this->getLogger()->info("§9break.yml§e を最新バージョンに対応させました。");
            }
        }

        if(file_exists($this->getDataFolder()."idplace.yml")){
            if(rename($this->getDataFolder()."idplace.yml",$this->getDataFolder()."CanPlace.yml")){
                $this->getLogger()->info("§9idplace.yml§e を最新バージョンに対応させました。");
            }
        }

        if(file_exists($this->getDataFolder()."idbreak.yml")){
            if(rename($this->getDataFolder()."idbreak.yml",$this->getDataFolder()."CanBreak.yml")){
                $this->getLogger()->info("§9idbreak.yml§e を最新バージョンに対応させました。");
            }
        }

        $this->place = new Config($this->getDataFolder()."Protected_Place_World.yml", Config::YAML);
        $this->break = new Config($this->getDataFolder()."Protected_Break_World.yml", Config::YAML);
        $this->idbreak = new Config($this->getDataFolder()."CanBreak.yml", Config::YAML);
        $this->idplace = new Config($this->getDataFolder()."CanPlace.yml", Config::YAML);
        $this->cantbreak = new Config($this->getDataFolder()."CantBreak.yml", Config::YAML);
        $this->cantplace = new Config($this->getDataFolder()."CantPlace.yml", Config::YAML);
        $this->cantuse = new Config($this->getDataFolder()."CantUse.yml", Config::YAML);

    }
    
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {

        if(!$sender instanceof Player && in_array($label, ['world', 'canbreak', 'canplace', 'cantbreak', 'cantplace'], true)){
            $sender->sendMessage('§cPlease run in-game');
            return true;
        }

        if(isset($args[1])){
            $world = $args[1];
        } else if($sender instanceof Player){
            $world = $sender->getWorld()->getFolderName();
        } else {//Consoleからだったら
            $sender->sendMessage('§cコマンドに第二引数(WorldName)を指定してください');
            return true;
        }
        
        switch($label) {

            case "world":

                if(isset($args[0]) === false) {
                    $sender->sendMessage('§cコマンドにワールド名を指定してください');
                    return true;
                }

                $sender->sendMessage("[WorldProtection] World:§e '".$args[0]."' §rを準備しています。");

                if(Server::getInstance()->getWorldManager()->loadWorld($args[0]) !== false) {
                    $sender->sendMessage("[WorldProtection] テレポート中...");
                    $sender->teleport(Server::getInstance()->getWorldManager()->getWorldByName($args[0])->getSpawnLocation());
                } else {
                    $sender->sendMessage("[WorldProtection] World:§e '".$args[0]."' §rは存在しません。");
                }

                return true;

            break;

            case "worldbreak":

                if(isset($args[0])){
                    
                    if($args[0] === 'on' || $args[0] === 'off') {
                    
                        switch($this->protection($this->break,$world,$args[0])){
                            case 0:
                                $sender->sendMessage('§cすでに破壊保護されています');
                            break;
                            case 1:
                                $sender->sendMessage("§9{$world} §eの破壊保護を無効にしました");
                            break;
                            case 2:
                                $sender->sendMessage("§9{$world} §eの破壊保護を有効にしました");
                            break;
                            case 3:
                                $sender->sendMessage("§cすでに破壊保護が無効化されています");
                            break;
                        }

                    } else {
                        $sender->sendMessage("使い方:\n/worldbreak <on/off> <World(任意)>");
                    }

                } else {
                    $sender->sendMessage("使い方:\n/worldbreak <on/off> <World(任意)>");
                }

            break;

            case "worldplace":

                if(isset($args[0])){

                    if($args[0] === 'on' || $args[0] === 'off') {
                    
                        switch($this->protection($this->place,$world,$args[0])){
                            case 0:
                                $sender->sendMessage('§cすでに設置が保護されています');
                            break;
                            case 1:
                                $sender->sendMessage("§9{$world} §eの設置保護を無効にしました");
                            break;
                            case 2:
                                $sender->sendMessage("§9{$world} §eの設置保護を有効にしました");
                            break;
                            case 3:
                                $sender->sendMessage("§cすでに設置保護が無効化されています");
                            break;
                        }

                    } else {
                        $sender->sendMessage("使い方:\n/worldplace <on/off> <World(任意)>");
                    }

                } else {
                    $sender->sendMessage("使い方:\n/worldplace <on/off> <World(任意)>");
                }

            break;

            case "canbreak":

                $hand = $sender->getInventory()->getItemInHand();
                if($hand->isNull() || !$hand instanceof ItemBlock){
                    $sender->sendMessage("§c例外にしたいブロックを手に持ってください");
                    return true;
                }
                $item = $hand->getVanillaName();

                if($this->break->exists($world) === false){
                    $sender->sendMessage("§e{$world}§c の破壊保護が有効になっていません！");
                    return true;
                }

                $data = $this->idbreak->get($world, []);

                if(in_array($item, $data, true)){

                    unset($data[array_search($item, $data)]);
                    $this->idbreak->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を例外ブロックから削除しました");

                } else {

                    array_push($data, $item);
                    $this->idbreak->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を例外ブロックに追加しました");

                }

                $this->idbreak->save();

            break;

            case "canplace":

                $hand = $sender->getInventory()->getItemInHand();
                if(!$hand instanceof LiquidBucket){
                    if($hand->isNull() || !$hand instanceof ItemBlock){
                        $sender->sendMessage("§c例外にしたいブロックを手に持ってください");
                        return true;
                    }
                }
                $item = $hand->getVanillaName();

                if($this->place->exists($world) === false){
                    $sender->sendMessage("§e{$world}§c の設置保護が有効になっていません！");
                    return true;
                }

                $data = $this->idplace->get($world, []);

                if(in_array($item, $data, true)){

                    unset($data[array_search($item, $data)]);
                    $this->idplace->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を例外ブロックから削除しました");

                } else {

                    array_push($data, $item);
                    $this->idplace->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を例外ブロックに追加しました");

                }

                $this->idplace->save();

            break;

            case "cantuse":

                $hand = $sender->getInventory()->getItemInHand();
                if($hand->isNull() || !$hand instanceof ItemBlock){
                    $sender->sendMessage("§c例外にしたいブロックを手に持ってください");
                    return true;
                }
                $item = $hand->getVanillaName();

                $data = $this->cantuse->get($world, []);

                if(in_array($item, $data, true)){

                    unset($data[array_search($item, $data)]);
                    $this->cantuse->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を使用不可ブロックから削除しました");

                } else {

                    array_push($data, $item);
                    $this->cantuse->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を使用不可ブロックに追加しました");

                }

                $this->cantuse->save();

            break;

            case "cantplace":

                $hand = $sender->getInventory()->getItemInHand();
                if(!$hand instanceof LiquidBucket){
                    if($hand->isNull() || !$hand instanceof ItemBlock){
                        $sender->sendMessage("§c例外にしたいブロックを手に持ってください");
                        return true;
                    }
                }
                $item = $hand->getVanillaName();

                if($this->place->exists($world)){
                    $sender->sendMessage("§e{$world}§c は既に設置保護がされています！");
                    return true;
                }

                $data = $this->cantplace->get($world, []);

                if(in_array($item, $data, true)){

                    unset($data[array_search($item, $data)]);
                    $this->cantplace->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を例外ブロックから削除しました");

                } else {

                    array_push($data, $item);
                    $this->cantplace->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を例外ブロックに追加しました");

                }

                $this->cantplace->save();

            break;
            
            case "cantbreak":

                $hand = $sender->getInventory()->getItemInHand();
                if($hand->isNull() || !$hand instanceof ItemBlock){
                    $sender->sendMessage("§c例外にしたいブロックを手に持ってください");
                    return true;
                }
                $item = $hand->getVanillaName();

                if($this->break->exists($world)){
                    $sender->sendMessage("§e{$world}§c は既に破壊保護がされています！");
                    return true;
                }

                $data = $this->cantbreak->get($world, []);

                if(in_array($item, $data, true)){

                    unset($data[array_search($item, $data)]);
                    $this->cantbreak->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を例外ブロックから削除しました");

                } else {

                    array_push($data, $item);
                    $this->cantbreak->set($world, $data);
                    $sender->sendMessage("§9{$item}§e を例外ブロックに追加しました");

                }

                $this->cantbreak->save();

            break;

        }

        return true;

    }

    public function onBreak(BlockBreakEvent $event) {

        $player = $event->getPlayer();
        $world = $player->getWorld()->getFolderName();
        $block = $event->getBlock();
        $item = $block->getName();

        if($this->getServer()->isOp($player->getName()) === false){

            if($this->break->exists($world)){

                $data = $this->idbreak->get($world, []);
                if(in_array($item, $data, true) === false) $event->cancel();

            } else {

                $data = $this->cantbreak->get($world, []);
                if(in_array($item, $data, true)) $event->cancel();

            }

        }


    }

    public function onPlace(BlockPlaceEvent $event) {

        $player = $event->getPlayer();
        $world = $player->getWorld()->getFolderName();
        $block = $event->getItem()->getBlock();
        $item = $block->getName();

        if($this->getServer()->isOp($player->getName()) === false){

            if($this->place->exists($world)){

                $data = $this->idplace->get($world, []);
                if(in_array($item, $data, true) === false) $event->cancel();

            } else {

                $data = $this->cantplace->get($world, []);
                if(in_array($item, $data, true)) $event->cancel();

            }

        }

    }

    public function onTap(PlayerInteractEvent $event){

        if($event->getAction() === PlayerInteractEvent::LEFT_CLICK_BLOCK) return;

        $player = $event->getPlayer();
        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld()->getFolderName();
        $item = $block->getName();

        if($this->getServer()->isOp($player->getName()) === false){

            $data = $this->cantuse->get($world, []);
            if(in_array($item, $data, true)) $event->cancel();

        }

    }

    public function onEmpty(PlayerBucketEmptyEvent $event){

        $player = $event->getPlayer();
        $world = $player->getWorld()->getFolderName();
        $item = $event->getBucket()->getVanillaName();

        if($this->getServer()->isOp($player->getName()) === false){

            if($this->place->exists($world)){

                $data = $this->idplace->get($world, []);
                if(in_array($item, $data, true) === false) $event->cancel();

            } else {

                $data = $this->cantplace->get($world, []);
                if(in_array($item, $data, true)) $event->cancel();

            }

        }

    }

    private function protection(Config $config,string $world,string $set):int{

        if($config->exists($world)){

            if($set === "on"){
                return 0;
            } else {
                $config->remove($world);
                $config->save();
                return 1;
            }

        } else {

            if($set === 'on'){
                $config->set($world,true);
                $config->save();
                return 2;
            } else {
                return 3;
            }

        }

    }


}