<?php

declare(strict_types=1);

namespace WorldGenerator;

use pocketmine\plugin\PluginBase;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\InvalidGeneratorOptionsException;

use WorldGenerator\Command\GenWorldCommand;
use WorldGenerator\Loader\ManagerLoader;

use WorldGenerator\Config\ConfigManager;

use WorldGenerator\Generator\WorldGeneratorManager;
use WorldGenerator\Generator\Generators\LandscapeGenerator;

use WorldGenerator\Pack\PackManager;
use WorldGenerator\UI\UIManager;

use WorldGenerator\Language\Translator;

final class WorldGenerator extends PluginBase{

    private ConfigManager $configManager;
    private WorldGeneratorManager $worldGeneratorManager;
    private PackManager $packManager;
    private UIManager $uiManager;
	private Translator $translator;
	
    // Инициализация плагина
    protected function onEnable(): void{
		$this->saveDefaultConfig();
		$this->translator = new Translator($this,(string) $this->getConfig()->get("language", "ru"));
        ManagerLoader::load($this);
		GeneratorManager::getInstance()->addGenerator(
			LandscapeGenerator::class,
			"wg_landscape",
			static function(string $options): ?InvalidGeneratorOptionsException{
				try{
					$data = json_decode($options,true,512,JSON_THROW_ON_ERROR);
				}catch(\JsonException){
					return new InvalidGeneratorOptionsException("Некорректные параметры LandscapeGenerator.");
				}

				if(
					!is_array($data)
					|| !isset($data["landscapePath"])
					|| !is_string($data["landscapePath"])
					|| $data["landscapePath"] === ""
					|| !isset($data["worldHeight"])
					|| !is_numeric($data["worldHeight"])
					|| (int)$data["worldHeight"] < 1
				){
					return new InvalidGeneratorOptionsException("Не указаны корректные landscapePath или worldHeight.");
				}
				return null;
			},
			true,
			true
		);
        $this->getServer()->getCommandMap()->register("WorldGenerator",new GenWorldCommand($this));
    }

    // Менеджеры
    public function setConfigManager(ConfigManager $manager): void{$this->configManager = $manager;}
    public function setGeneratorManager(WorldGeneratorManager $manager): void{$this->worldGeneratorManager = $manager;}
    public function setPackManager(PackManager $manager): void{$this->packManager = $manager;}
    public function setUIManager(UIManager $manager): void{$this->uiManager = $manager;}
    public function getConfigManager(): ConfigManager{return $this->configManager;}
    public function getWorldGeneratorManager(): WorldGeneratorManager{return $this->worldGeneratorManager;}
    public function getPackManager(): PackManager{return $this->packManager;}
    public function getUIManager(): UIManager{return $this->uiManager;}
    // Глобальный экземпляр
    public static function getInstance(): WorldGenerator{return self::$instance;}
	public function getSessionManager(): \WorldGenerator\Config\SessionManager{return $this->configManager->getSessionManager();}
	public function getTranslator(): Translator{return $this->translator;}
}