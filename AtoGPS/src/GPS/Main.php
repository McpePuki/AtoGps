<?php
namespace GPS;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\level\particle\HeartParticle;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket; // 커스텀 UI 관련
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket; // 커스텀 UI 관련
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;


class Main extends PluginBase implements Listener {

  public $gps = [];

    public function onEnable() {
        $this-> getServer()-> getPluginManager()-> registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new GpsTask($this), 10);
        $this->data =new Config($this->getDataFolder(). 'GpsList.yml', Config::YAML);
        $this->db = $this->data->getAll();
        $this->gdata =new Config($this->getDataFolder(). 'GpsData.yml', Config::YAML);
        $this->gdb = $this->gdata->getAll();
    }

    public function save(){
      $this->data->setAll($this->db);
      $this->data->save();
      $this->gdata->setAll($this->gdb);
      $this->gdata->save();
    }

    public function onUIevnet (DataPacketReceiveEvent $event) {
      $packet = $event->getPacket ();
      $player = $event->getPlayer();
      $name = $player->getName();
       if ($packet instanceof ModalFormResponsePacket) {
         if($packet->formId === 78767){
           $button = json_decode ( $packet->formData, true );
           if($button == null){

             $button = 0;
           }
           $data = $this->gdb ['목적지'][json_decode ($packet->formData, true)+1];
           if(isset($this->db['플레이어'][$name])){
             unset($this->db['플레이어'][$name]);
           }
           $xyz = $this->db['목적지'][$data];
           $this->db['플레이어'][$name] = [$xyz[0], $xyz[1], $xyz[2], $xyz[3]];
           $this->save();
           $player->sendMessage($data.'의 길을 찾습니다. 파티클을 따라가 주세요.');
         }
       }
       }

    public function GPSUI($player){
      foreach($this->db['목적지'] as $key => $value){
        $list[] =  array('text' => $key ."\n");
      }
      $button = [
        'type' => 'form',
        'title' => '네비게이션 UI',
        'content' => "§c▶ §f원하시는 목적지를 선택해주세요.",
        'buttons' => $list
      ];
      return json_encode($button);
    }

    public function onCommand(CommandSender $sender, Command $command, $lable , array $args) :bool {
      $cmd = $command->getName();
      if($cmd == '네비게이션'){
        if(!isset($args[0])) {
          $sender->sendMessage('/네비게이션 추가 [목적지명]');
          return true;
        }
        switch ($args[0]) {
          case '추가':
          $this->db['목적지'][$args[1]] = [$sender->x , $sender->y, $sender->z, $sender->level->getName()];
          $this->gdb['목적지'][count($this->db['목적지'])] = $args[1];
          $this->save();
          $sender->sendMessage('성공적으로 목적지를 추가했습니다.');
            break;
            return true;
        }
        return true;
      }
    }

    public function onTouch(PlayerInteractEvent $event) {
        $player = $event-> getPlayer();
        $name = $player->getName();
        if($player-> getInventory()-> getItemInHand()-> getId() == 339 && $player->getInventory()->getItemInHand()->getDamage() == 0) {
          if(!isset($this->db['목적지'])){
            $player->sendMessage('아직 까지 생성된 목적지는 없습니다.');
          return true;
          }
          $pk = new ModalFormRequestPacket ();
          $pk->formId = 78767;
          $pk->formData = $this->GPSUI($player);
          $player->dataPacket ($pk);
        }
    }
}
class GpsTask extends Task {

  private $owner;
    public function __construct(Main $owner) {
      $this->owner = $owner;
    }
    public function onRun(int $currentTick) {
      foreach($this->owner->getServer()->getOnlinePlayers() as $player){
        $name = $player->getName();
        if(isset($this->owner->db['플레이어'][$name])){
        $xyz = $this->owner->db['플레이어'][$name];
        $p = new Position($xyz[0], $player-> y, $xyz[2], $player-> getLevel());
        if($player->distance($p) < 15){
          unset($this->owner->db['플레이어'][$name]);
          $player->sendMessage('목적지에 도착 했습니다.');
          return true;
        }
        $gps = $player-> distance($p);
        $x =  $player-> x + 2 * ($p-> x - $player-> x)/$gps;
        $z = $player-> z + 2 * ($p-> z - $player-> z)/$gps;
        $player-> getLevel()-> addParticle(new HeartParticle(new Position($x, $player-> y +2, $z, $player-> getLevel())));
    }
  }
  }
}
